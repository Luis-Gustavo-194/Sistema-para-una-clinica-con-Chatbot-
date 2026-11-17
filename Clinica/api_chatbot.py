from fastapi import FastAPI
from pydantic import BaseModel
import pickle
from preprocesamiento import limpiar_texto

# El umbral de confianza ya no vive aquí: Laravel (config/chatbot.php) decide con el puntaje
# si acepta la intención, pregunta "¿Quisiste decir…?" con las candidatas o no la entiende.
# Referencia medida con frases nuevas: las bien clasificadas quedan por encima de 0.

# Cuántas intenciones probables se devuelven para la aclaración
CANTIDAD_CANDIDATAS = 3

# 1. Inicializamos la API
app = FastAPI(title="Motor NLP - Citas Médicas")

# 2. Cargamos el cerebro de la IA al encender el servidor
print("Cargando modelos...")
with open('vectorizador.pkl', 'rb') as f:
    vectorizador = pickle.load(f)

with open('modelo_chatbot.pkl', 'rb') as f:
    modelo = pickle.load(f)
print("¡Modelos cargados correctamente!")

# 3. Definimos la estructura de los datos que Laravel nos enviará
class MensajeUsuario(BaseModel):
    texto: str

# Comprobación de que el servicio está arriba (healthcheck de Railway)
@app.get("/salud")
def salud():
    return {"estado": "ok"}

# 4. Creamos el Endpoint (La puerta de entrada para Laravel)
@app.post("/predecir")
def predecir_intencion(mensaje: MensajeUsuario):
    # Limpiamos el texto usando tu función
    texto_limpio = limpiar_texto(mensaje.texto)
    frase_procesada = " ".join(texto_limpio)

    # Manejo de fallback (si el usuario escribe cosas sin sentido)
    if not frase_procesada:
        return {"texto_original": mensaje.texto, "intencion": "desconocida", "confianza": 0.0, "candidatas": []}

    # Convertimos a números
    vector = vectorizador.transform([frase_procesada])

    # Si ninguna palabra existe en el vocabulario, el vector queda vacío y el
    # modelo devolvería siempre la misma clase ("saludo"): lo tratamos como desconocido
    if vector.nnz == 0:
        return {"texto_original": mensaje.texto, "intencion": "desconocida", "confianza": 0.0, "candidatas": []}

    # Puntaje de cada intención, de mayor a menor: la primera es la predicha
    puntajes = modelo.decision_function(vector)[0]
    orden = puntajes.argsort()[::-1][:CANTIDAD_CANDIDATAS]
    candidatas = [
        {"intencion": str(modelo.classes_[i]), "puntaje": round(float(puntajes[i]), 3)}
        for i in orden
    ]

    # FastAPI convierte automáticamente este diccionario en formato JSON para Laravel
    return {
        "texto_original": mensaje.texto,
        "intencion": candidatas[0]["intencion"],
        "confianza": candidatas[0]["puntaje"],
        "candidatas": candidatas,
    }

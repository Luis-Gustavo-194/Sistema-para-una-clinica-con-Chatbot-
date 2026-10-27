from fastapi import FastAPI
from pydantic import BaseModel
import pickle
from preprocesamiento import limpiar_texto

# Puntaje mínimo (margen de LinearSVC) para aceptar una intención.
# Medido con frases nuevas: las bien clasificadas quedan por encima de 0
# y las mal clasificadas por debajo, así que es mejor pedir que reformule.
UMBRAL_CONFIANZA = 0.0

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

# 4. Creamos el Endpoint (La puerta de entrada para Laravel)
@app.post("/predecir")
def predecir_intencion(mensaje: MensajeUsuario):
    # Limpiamos el texto usando tu función
    texto_limpio = limpiar_texto(mensaje.texto)
    frase_procesada = " ".join(texto_limpio)

    # Manejo de fallback (si el usuario escribe cosas sin sentido)
    if not frase_procesada:
        return {"texto_original": mensaje.texto, "intencion": "desconocida", "confianza": 0.0}

    # Convertimos a números
    vector = vectorizador.transform([frase_procesada])

    # Si ninguna palabra existe en el vocabulario, el vector queda vacío y el
    # modelo devolvería siempre la misma clase ("saludo"): lo tratamos como desconocido
    if vector.nnz == 0:
        return {"texto_original": mensaje.texto, "intencion": "desconocida", "confianza": 0.0}

    # Predecimos y medimos qué tan seguro está el modelo
    puntajes = modelo.decision_function(vector)[0]
    indice = puntajes.argmax()
    confianza = float(puntajes[indice])

    if confianza < UMBRAL_CONFIANZA:
        intencion_predicha = "desconocida"
    else:
        intencion_predicha = str(modelo.classes_[indice])

    # FastAPI convierte automáticamente este diccionario en formato JSON para Laravel
    return {
        "texto_original": mensaje.texto,
        "intencion": intencion_predicha,
        "confianza": round(confianza, 3)
    }

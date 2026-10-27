import pickle
from preprocesamiento import limpiar_texto

print("Cargando el cerebro del chatbot...")

# 1. Cargamos las herramientas entrenadas
with open('vectorizador.pkl', 'rb') as f:
    vectorizador = pickle.load(f)
    
with open('modelo_chatbot.pkl', 'rb') as f:
    modelo = pickle.load(f)

print("¡Chatbot listo! Escribe 'salir' para terminar.\n")
print("-" * 50)

# 2. Bucle infinito para chatear
while True:
    mensaje = input("Tú (Paciente): ")
    
    if mensaje.lower() == 'salir':
        print("Chat finalizado.")
        break
        
    # 3. Limpiamos el texto usando la herramienta que creaste primero
    texto_limpio = limpiar_texto(mensaje)
    frase_procesada = " ".join(texto_limpio)
    
    # Si el mensaje quedó vacío tras la limpieza (ej. el usuario solo escribió "el la")
    if not frase_procesada:
        print("Chatbot: No logré entender la intención. ¿Puedes reformularlo?")
        continue

    # 4. Convertimos el texto a números y predecimos
    vector = vectorizador.transform([frase_procesada])
    intencion_predicha = modelo.predict(vector)[0]
    
    print(f"-> [Intención detectada por la IA]: {intencion_predicha}")
    print("-" * 50)
import json
import pickle
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.svm import LinearSVC
# Importamos tu función de limpieza creada en el paso anterior
from preprocesamiento import limpiar_texto 

print("1. Cargando el dataset...")
with open('dataset.json', 'r', encoding='utf-8') as archivo:
    datos = json.load(archivo)

textos_entrenamiento = []
etiquetas_entrenamiento = []

print("2. Limpiando y procesando los ejemplos de entrenamiento...")
for intencion in datos['intenciones']:
    etiqueta = intencion['etiqueta']
    for patron in intencion['patrones']:
        # Usamos tu herramienta para limpiar cada frase del JSON
        texto_limpio = limpiar_texto(patron)
        # Unimos las palabras limpias en una sola frase con espacios
        frase_procesada = " ".join(texto_limpio)
        
        textos_entrenamiento.append(frase_procesada)
        etiquetas_entrenamiento.append(etiqueta)

print("3. Entrenando la Inteligencia Artificial...")
# Convertimos el texto en números (Vectores) para que la IA los entienda
vectorizador = TfidfVectorizer()
X = vectorizador.fit_transform(textos_entrenamiento)
y = etiquetas_entrenamiento

# Usamos el algoritmo Support Vector Classifier (muy bueno para textos)
modelo = LinearSVC()
modelo.fit(X, y)

print("4. Guardando el cerebro del chatbot...")
# Guardamos el vocabulario y el modelo entrenado en archivos físicos
with open('vectorizador.pkl', 'wb') as f:
    pickle.dump(vectorizador, f)
    
with open('modelo_chatbot.pkl', 'wb') as f:
    pickle.dump(modelo, f)

print("¡Entrenamiento finalizado con éxito! Archivos .pkl generados.")


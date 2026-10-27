import spacy

nlp = spacy.load("es_core_news_sm")

def limpiar_texto(texto_usuario):
    documento = nlp(texto_usuario)
    palabras_limpias = []
    
    for palabra in documento:
        if not palabra.is_stop and not palabra.is_punct:
            palabras_limpias.append(palabra.lemma_.lower())
            
    return palabras_limpias

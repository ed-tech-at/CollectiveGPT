# Verwenden Sie ein Basis-Image mit Python
FROM python:3.9-slim

# Arbeitsverzeichnis erstellen
WORKDIR /app

# Abhängigkeiten kopieren und installieren
COPY requirements.txt requirements.txt
RUN pip install -r requirements.txt

# Anwendungscode kopieren
COPY . .

# Port freigeben
EXPOSE 3002

# Befehl zum Starten der Anwendung
CMD ["python", "app.py"]

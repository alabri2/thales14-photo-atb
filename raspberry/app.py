import os
import time
import json
import cv2
import sqlite3
import sys
import requests
from datetime import datetime

# === CONFIGURATION ===
COMMAND_PATH = "/var/www/html/commande.json"
IMAGE_FOLDER = "/var/www/html/assets/images"
DB_PATH = "/var/www/html/data/BdD_Thales14_V3.sqlite"
DELAY_24H = 60 * 60 * 24  # 24 hours
PICO_IP = "172.20.10.4"
PICO_URL = f"http://{PICO_IP}:5000/command"

# === SETUP ===
os.makedirs(IMAGE_FOLDER, exist_ok=True)
camera = cv2.VideoCapture(0)
if not camera.isOpened():
    print("[ERROR] Camera not detected.")
    exit()

# === FUNCTIONS ===

def send_led_command(mode):
    try:
        response = requests.get(f"{PICO_URL}?mode={mode}", timeout=2)
        print(f"[PICO] Response: {response.text}")
    except Exception as e:
        print(f"[PICO] Error: {e}")

def take_photo(filename, led_mode="off"):
    print(f"[INFO] Taking photo: {filename}")
    send_led_command(led_mode)
    time.sleep(1)

    ret, frame = camera.read()
    if not ret:
        print("[ERROR] Capture failed.")
        return

    full_path = os.path.join(IMAGE_FOLDER, filename)
    cv2.imwrite(full_path, frame)
    print(f"[INFO] Photo saved: {full_path}")

    # save_to_database(filename)
    send_led_command("off")

def save_to_database(filename):
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        path = filename
        cursor.execute("""
            INSERT INTO PHOTO (titre, chemin, dateP, heureP, userID, supr)
            VALUES (?, ?, date('now'), time('now'), ?, 'non')
        """, (filename, path, 1))
        cursor.execute("""
            INSERT INTO LOGS (userID, dateL, heureL, evenement, plus)
            VALUES (?, date('now'), time('now'), ?, ?)
        """, (1, "Information", f"Photo taken (script): {filename}"))
        conn.commit()
        conn.close()
        print("[INFO] Database insertion successful.")
    except Exception as e:
        print(f"[DB ERROR] {e}")

# === Terminal trigger ===
if len(sys.argv) > 1 and sys.argv[1] == "terminal":
    filename = "photo_term_" + datetime.now().strftime("%Y%m%d_%H%M%S") + ".jpg"
    take_photo(filename, led_mode="on")
    save_to_database(filename)
    camera.release()
    exit()

# === Main script ===
print("[INFO] Starting - JSON + 24h + LED")
last_photo_time = time.time()

while True:
    try:
        with open(COMMAND_PATH, "r") as f:
            command = json.load(f)
    except:
        command = {}

    action = command.get("action", "none")
    led_mode = command.get("led", "off")
    photo_name = command.get("nom", "")

    # Photo triggered by web interface
    if action == "prendre_photo" and photo_name:
        take_photo(photo_name, led_mode)
        command["action"] = "none"
        with open(COMMAND_PATH, "w") as f:
            json.dump(command, f, indent=4)
        time.sleep(2)

    # Automatic capture every 24 hours
    if (time.time() - last_photo_time) > DELAY_24H:
        auto_filename = "photo_auto_" + datetime.now().strftime("%Y%m%d_%H%M%S") + ".jpg"
        take_photo(auto_filename, led_mode="auto")
        last_photo_time = time.time()
        save_to_database(auto_filename)

    time.sleep(1)

import network
import socket
from bh1750 import BH1750
from machine import Pin, I2C
import time

# === LED GPIO ===
led = Pin(15, Pin.OUT)

# === Light Sensor ===
i2c0_sda = Pin(0)
i2c0_scl = Pin(1)
i2c = I2C(0, sda=i2c0_sda, scl=i2c0_scl, freq=400000)
appareil = i2c.scan()
light_sensor = BH1750(0x23, i2c)

# === Wi-Fi Connection ===
ssid = 'YOUR_WIFI_SSID'
password = 'YOUR_WIFI_PASSWORD'

wlan = network.WLAN(network.STA_IF)
wlan.active(True)
wlan.connect(ssid, password)

print("Wi-Fi connection in progress...")
while not wlan.isconnected():
    time.sleep(1)
print("Connected :", wlan.ifconfig()[0])

# === HTTP server ===
addr = socket.getaddrinfo('0.0.0.0', 5000)[0][-1]
s = socket.socket()
s.bind(addr)
s.listen(1)
print("Server listening on port 5000")

while True:
    # === Listening for a request from Raspberry Pi ===
    cl, addr = s.accept()
    print("Client connected from", addr)
    request = cl.recv(1024).decode()
    print("Request received :", request)

    # === Applying the requested mode for the LED ===
    led_mode = "off"
    if "/command?mode=on" in request:
        led.value(1) # turn the light on
        led_mode = "on"
        
    elif "/command?mode=off" in request:
        led.value(0) # turn the light off
        led_mode = "off"
        
    elif "/command?mode=auto" in request:
        # Auto = LED ON if the light sensor gives a value under or equal 30
        print("Light_level :",light_sensor.measurement)
        if light_sensor.measurement <= 30:
            led.value(1)
            led_mode = "auto -> ON"
        else:
            led.value(0)
            led_mode = "auto -> OFF"

    cl.send("HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\n")
    cl.send("LED mode: " + led_mode)
    cl.close()

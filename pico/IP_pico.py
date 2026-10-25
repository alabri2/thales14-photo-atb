import network
import time
wlan = network.WLAN(network.STA_IF)
wlan.active(True)
wlan.connect("YOUR_WIFI_SSID","YOUR_WIFI_PASSWORD")
#wlan.ifconfig(("172.20.10.9","255.255.0.0", "172.20.10.14", "0.0.0.0"))
time.sleep(2)
print(wlan.ifconfig()[0])
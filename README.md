# 差勤管理 Line Bot
Node.js Line Bot integrated with leave management system via MQTT.
## Setup
```bash
npm install
cp .env.example .env
# Fill in your Line credentials
node index.js
```
## Requirements
Node.js 18+
Mosquittto MQTT broker on localhost:1883
Laravel API on localhost:8000
ngrok for webhook
## Commands
上班 - Clock in
下班 - Clock out
我的假期 - Check leave balances
説明 - Help

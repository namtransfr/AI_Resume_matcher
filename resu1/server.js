// server.js
import express from 'express';
import dotenv from 'dotenv';

// โหลด environment variables จาก Railway
dotenv.config();

const app = express();

// อ่าน port จาก environment variable ของ Railway
const PORT = process.env.PORT || 3000;

// ตัวอย่าง route แรก
app.get('/', (req, res) => {
  res.send('Hello from Railway + incredible-simplicity!');
});

// ถ้า API ของคุณต้องเรียก GROQ / Supabase
// ตัวอย่างใช้งาน environment variable
app.get('/info', (req, res) => {
  const groqApiKey = process.env.GROQ_API_KEY;
  const groqApiUrl = process.env.GROQ_API_URL;
  const supabaseKey = process.env.SUPABASE_KEY;
  const supabaseUrl = process.env.SUPABASE_URL;

  res.json({
    groqApiKey: groqApiKey ? 'SET ✅' : 'MISSING ❌',
    groqApiUrl: groqApiUrl ? 'SET ✅' : 'MISSING ❌',
    supabaseKey: supabaseKey ? 'SET ✅' : 'MISSING ❌',
    supabaseUrl: supabaseUrl ? 'SET ✅' : 'MISSING ❌'
  });
});

// Start server
app.listen(PORT, '0.0.0.0', () => {
  console.log(`Server running on port ${PORT}`);
});
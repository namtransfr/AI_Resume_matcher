<?php
// ตัวอย่างฟังก์ชัน helper
function processResume($resume) {
    // ทำอะไรก็ได้ เช่น return ข้อมูล dummy
    return [
        'status' => 'success',
        'message' => 'Resume processed',
        'file_name' => $resume['name'] ?? null
    ];
}
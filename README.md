# BEACON TEAM

ระบบจัดการจำนวนกรง Minecraft สำหรับ XAMPP, PHP 8+, SQLite และ PDO

## เริ่มใช้งาน

เปิด `http://localhost/cost/` เพื่อเข้าสู่ระบบ ระบบจะสร้างฐานข้อมูล `data/cages.sqlite` และตารางที่จำเป็นโดยอัตโนมัติ หลังเข้าสู่ระบบจะแยกหน้าให้ตามสิทธิ์:

- `index.php` หน้าเข้าสู่ระบบของผู้ใช้ทั่วไป ใช้เฉพาะชื่อและไม่มีช่องรหัสผ่าน
- `admin.php` หน้าเข้าสู่ระบบและหน้าจัดการของ ADMIN ซึ่งต้องใช้รหัสผ่าน
- `user.php` หน้าทำรายการของผู้ใช้ทั่วไป

ทั้งสองหน้าตรวจสอบสิทธิ์ฝั่งเซิร์ฟเวอร์ ผู้ใช้ทั่วไปจึงเปิดหน้า ADMIN โดยตรงไม่ได้

ผู้ใช้ทั่วไปเข้าสู่ระบบด้วยชื่อที่ ADMIN ลงทะเบียนไว้โดยไม่ต้องใช้รหัสผ่าน ส่วนบัญชี ADMIN ต้องกรอกรหัสผ่าน

คำขอ `POST` หลังเข้าสู่ระบบต้องส่ง header `X-CSRF-Token` ที่ได้รับจากผลลัพธ์การล็อกอินหรือ `GET /api.php?route=auth/me`

## API หลัก

- `POST /cost/api.php?route=auth/login`
- `POST /cost/api.php?route=auth/logout`
- `GET /cost/api.php?route=cages/global-balance`
- `GET /cost/api.php?route=cages/global-history`
- `POST /cost/api.php?route=cages/transactions`
- `GET /cost/api.php?route=admin/overview`
- `GET /cost/api.php?route=admin/pending-reviews`
- `POST /cost/api.php?route=admin/review-batches/{batchId}/approve`
- `POST /cost/api.php?route=admin/review-batches/{batchId}/reject`

กฎตรวจความเสี่ยงอยู่ใน `risk_engine.php`

## Deploy บน Vercel

ระบบรองรับสองสภาพแวดล้อม:

- XAMPP ใช้ SQLite จาก `data/cages.sqlite`
- Vercel ใช้ PostgreSQL เมื่อกำหนด Environment Variable ชื่อ `DATABASE_URL`

ขั้นตอนนำขึ้นระบบ:

1. สร้างฐานข้อมูล PostgreSQL และคัดลอก connection URL
2. ตั้ง `DATABASE_URL` ใน Vercel สำหรับ Production, Preview และ Development ตามที่ต้องการ
3. ติดตั้ง Vercel CLI แล้วรัน `vercel` จากโฟลเดอร์โปรเจกต์
4. หากต้องการย้ายข้อมูล SQLite เดิม ให้ตั้ง `DATABASE_URL` บนเครื่องแล้วรัน `php migrate_to_postgres.php` เพียงครั้งเดียว
5. Deploy production ด้วย `vercel --prod`

ไฟล์ `vercel.json` ใช้ PHP Community Runtime และส่งทุก route ผ่าน `api/index.php` ส่วน session บน Vercel จะจัดเก็บใน PostgreSQL อัตโนมัติ

> `migrate_to_postgres.php` จะล้างข้อมูลในฐานข้อมูลปลายทางก่อนนำเข้าข้อมูลจาก SQLite จึงควรใช้กับฐานข้อมูลใหม่เท่านั้น

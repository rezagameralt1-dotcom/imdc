import "../css/app.css"; // اضافه شده برای لود استایل‌ها
import "./bootstrap";
import { login, me, logout } from "./lib/auth";

window.spaAuth = { login, me, logout };
console.log("spaAuth آماده‌ست. نمونه‌ی تست:");
console.log('await spaAuth.login("test@example.com","secret");');
console.log("await spaAuth.me();");
console.log("await spaAuth.logout();");

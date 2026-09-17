<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Giới thiệu | MOFI</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="auth-page"><main class="auth-card info-card"><a class="brand" href="/"><span class="brand-mark">M</span><span>MOFI<small>Hiểu tiền, đầu tư tốt hơn.</small></span></a>
@if($page==='pricing')
<h1>Trải nghiệm MOFI miễn phí</h1><p class="auth-lead">Bản demo dành cho học tập và đánh giá dự án. Bạn có thể ghi giao dịch ảo, quản lý mục tiêu và khám phá dữ liệu.</p><h2>MOFI Pro · Dự kiến</h2><p>Phân tích nâng cao, tích hợp nhà cung cấp giá và trợ lý AI là định hướng mở rộng. Chưa công bố giá và chưa hỗ trợ thanh toán.</p>
@elseif($page==='policies')
<h1>Dữ liệu & quyền riêng tư</h1><p>Bản demo sử dụng tài khoản, danh mục và tiền mô phỏng. Không kết nối tài khoản ngân hàng, không đặt lệnh chứng khoán và không nhận thanh toán.</p><p>Tên hiển thị, email, mật khẩu đã băm và dữ liệu bạn nhập được lưu trên PostgreSQL do Supabase cung cấp. Chỉ sử dụng thông tin thử nghiệm trong bản đánh giá này.</p><p>Trang Thị trường có thể tải dữ liệu công khai từ Binance qua máy chủ Laravel. Danh mục cá nhân không được gửi cho nguồn dữ liệu đó. Giá thật và giá mô phỏng được trình bày riêng.</p><p>Các gợi ý không phải tư vấn đầu tư. Không nhập dữ liệu tài chính nhạy cảm vào môi trường demo.</p>
@elseif($page==='products')
<h1>Công cụ cho hành trình tài chính</h1><p>Dashboard hiển thị tài sản, dòng tiền, lãi/lỗ và lịch sử 30 ngày. Các công cụ mục tiêu, theo dõi giá, cảnh báo và công việc lưu dữ liệu theo tài khoản.</p><p>Phòng mô phỏng cho phép thử mức giảm giá trên danh mục. Bài học giúp bạn hiểu dòng tiền, phân bổ tài sản và lãi kép.</p>
@else
<h1>Hiểu tiền để chủ động hơn</h1><p>MOFI là dự án demo quản lý tài chính cá nhân và học đầu tư, xây dựng bằng Laravel, React, TypeScript và PostgreSQL trên Supabase.</p><p>Dự án kết hợp số liệu tính từ giao dịch với giao diện trực quan. Giao dịch ảo giúp thử nghiệm an toàn; dữ liệu thị trường thật được ghi rõ nguồn và thời điểm lấy.</p>
@endif
<div class="hero-actions"><a href="/dashboard" class="button button-blue">Khám phá dashboard</a><a href="/" class="button button-ghost">Trang chủ</a></div></main></body></html>

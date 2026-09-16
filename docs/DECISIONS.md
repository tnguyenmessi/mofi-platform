# MOFI Architecture Decision Record

Ghi các quyết định đã chốt, lý do, người duyệt, ngày và ảnh hưởng. Không sửa lịch sử quyết định; nếu đổi, tạo quyết định mới liên kết quyết định cũ.

## ADR-001 - Application stack

- Status: Accepted
- Decision: Laravel/PHP làm backend, React/TypeScript qua Inertia làm frontend, Tailwind CSS cho UI.
- Reason: Một application boundary, phù hợp dashboard tương tác và giữ nghiệp vụ tài chính ở server.
- Consequence: Không tạo backend Node riêng trong MVP.

## ADR-002 - Database hosting

- Status: Accepted
- Decision: PostgreSQL trên Supabase.
- Reason: Relational constraints, numeric types, managed backups và môi trường phù hợp.
- Consequence: Secrets của Supabase chỉ ở server/local environment.

## ADR-003 - Market data

- Status: Proposed
- Decision: Mock provider trước; provider thật sau khi xác nhận licensing, symbols, latency và redistribution rights.
- Consequence: UI phải hiển thị trạng thái mock/stale rõ ràng trong giai đoạn đầu.

## ADR-004 - Financial writes

- Status: Proposed
- Decision: Giao dịch gốc bất biến về mặt audit; điều chỉnh bằng record liên kết và tính lại projection.
- Consequence: Không cho sửa tùy tiện một cột holding mà bỏ qua transaction history.

## Pending decisions

- Người duyệt MVP và ngày duyệt.
- Công thức tỷ suất sinh lời sẽ hỗ trợ ở bản nào.
- Có hỗ trợ nhiều tiền tệ/nợ phải trả ở MVP hay không.
- Provider dữ liệu và mức độ realtime/EOD.
- Chính sách retention, export và xóa dữ liệu.

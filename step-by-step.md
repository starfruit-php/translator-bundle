# Hướng dẫn cài đặt và sử dụng

## Google Cloud Platform (GCP)

1. Cài đặt GCP cho hệ thống và chạy active tài khoản (theo tài liệu nộ bộ),

2. Kiểm tra GCP bằng lệnh `gcloud auth print-identity-token`, nếu cài đặt thành công thì kết quả trả ra là 1 chuỗi token.

## Cấu hình DataObject

Bổ sung cấu hình trong file `config/config.yaml` theo mẫu:

```bash
starfruit_translator:
    object:
        class_need_translate: # danh sách các Class có trường cần dịch
            Post: # Tên Class
                field_need_translate: # danh sách trường cần dịch (input, textarea hoặc wysiwyg)
                    - name
                    - description
                    - content
            Category:
                field_need_translate:
                    - name
```

## Cấu hình Document

Các bước khi tạo mới hoặc bổ sung cho document để có thể dịch, cần kiểm tra và đảm bảo các bước 3, 7, 8:
1. Xác định document nguồn bản Tiếng Việt `vi` - *Source*
2. Xác định document cần dịch - *Target*, nếu chưa có thì tạo mới theo dạng Page hoặc Snippet
3. Nếu cấu trúc thông tin của *Target* đã giống với *Source*, thực hiện bước 7, nếu không thực hiện theo các bước 4 -> 6
4. Trên cây thư mục, Chuột phải vào *Source* chọn `Copy`
5. Trên cây thư mục, Chuột phải vào *Target* chọn `Paste` -> `Paste only contents here`
6. Trên thanh công cụ, chọn chức năng `Reload` (biểu tượng tải lại - vòng tròn có đầu mũi tên) để tải lại nội dung mới cho *Target*
7. Chọn tab `Navigation & Properties` -> Chọn `Language` phù hợp cho *Target* -> Bấm `Save` hoặc `Save & Publish` để lưu cấu hình
8. Nếu chưa có nút dịch, cần liên kết *Source* và *Target* theo bước 9 -> 12
9. Trên thanh công cụ, chọn chức năng `Translation` (biểu tượng dịch/từ điển), mở mũi tên chọn `Link existing Document`
10. Trên cây thư mục, đặt chuột vào *Source* saud đó kéo thả (drag & drop) vào ô `Translation` của hộp thông tin
11. Xác nhận thông tin `Language` hiển thị `Vietnamese [vi]`
12. Bấm `Apply` để xác nhận

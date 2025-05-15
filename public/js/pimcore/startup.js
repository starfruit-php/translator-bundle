pimcore.registerNS("pimcore.plugin.StarfruitTranslatorBundle");

pimcore.plugin.StarfruitTranslatorBundle = Class.create({

    initialize: function () {
        document.addEventListener(pimcore.events.pimcoreReady, this.pimcoreReady.bind(this));

        this.popupContainer = null;
    },

    pimcoreReady: function (e) {
        var toolbar = pimcore.globalmanager.get("layout_toolbar");

        this.navTransGuide = Ext.get('pimcore_menu_search').insertSibling('<li id="pimcore_menu_navTransGuide" data-menu-tooltip="Hướng dẫn Chức năng Dịch"\
            class="pimcore_menu_item pimcore_menu_needs_children"><img src="/bundles/pimcoreadmin/img/flat-color-icons/translation.svg"></li>\
            ', 'after');

        this.navTransGuide.on("mousedown", function() {
            this.popupContainer = this.popup();
        }.bind(this));

        pimcore.helpers.initMenuTooltips();
    },

    popup: function() {
        let translationWindow = Ext.create('Ext.window.Window', {
            title: 'Hướng dẫn liên kết nội dung Tiếng Việt cho Document',
            width: 800,
            modal: true,
            layout: 'vbox',
            bodyPadding: 10,
            y: 100,
            id: 'translationWindow',
            items: [
                {
                    xtype: 'tabpanel',
                    activeTab: 0,
                    items: [
                        {
                            title: 'Các bước liên kết nội dung',
                            xtype: 'panel',
                            bodyPadding: 10,
                            html: `
                                <h3>1. Chọn nội dung cần dịch</h3>
                                <h3>2. Nếu nút dịch đã hiện ở góc trên bên phải thì bấm dịch để bắt đầu</h3>
                                <h3>3. Nếu nút dịch chưa hiển thị, cần liên kết với nội dung Tiếng Việt trước</h3>
                                <h4>3.1 Trên thanh công cụ, chọn chức năng <b>Translation</b> (biểu tượng dịch/từ điển)<br> -> mở mũi tên chọn <b>Link existing Document</b></h3>
                                <h4>3.2 Trên cây thư mục, đặt chuột vào mục có nội dung Tiếng Việt tương ứng<br> -> kéo thả (drag & drop) vào ô <b>Translation</b> của hộp thông tin</h3>
                                <h4>3.3 Xác nhận thông tin Language hiển thị là <b>Vietnamese [vi]</b></h3>
                                <h4>3.4 Bấm <b>Apply</b> để xác nhận, nút dịch sẽ hiển thị</h3>
                            `
                        },
                        {
                            title: 'Xem video hướng dẫn',
                            xtype: 'panel',
                            layout: 'fit',
                            items: [
                                {
                                    xtype: 'box',
                                    autoEl: {
                                        tag: 'div',
                                        html: `
                                            <video width="800px" controls autoplay muted>
                                                <source src="/bundles/starfruittranslator/video/mapping_vi_content_berfor_translate.mp4" type="video/mp4">
                                                Trình duyệt của bạn không hỗ trợ video.
                                            </video>
                                        `
                                    }
                                }
                            ]
                        }
                    ]
                }
            ]
        });
        translationWindow.show();

        return translationWindow;
    }
});

var StarfruitTranslatorBundlePlugin = new pimcore.plugin.StarfruitTranslatorBundle();

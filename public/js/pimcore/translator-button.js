
var csrfToken = pimcore.settings.csrfToken;
var myHeaders = {
    'X-Pimcore-CSRF-Token': csrfToken
};
var requestOptions = {
    method: 'POST',
    headers: myHeaders
};

document.addEventListener(pimcore.events.postOpenDocument, (e) => {
    var item = e.detail.document;
    var id = item.id;

    var type = item.type;
    var validType = type == 'page' || type == 'snippet';

    var docLang = item.data.properties.language.data;
    var isTransLang = docLang && docLang != 'vi';

    var translations = item.data.translations;
    var sourceId = translations?.vi;

    if (validType && isTransLang && sourceId) {
        initTranslateDocumentButton();
    } else {
        console.log('Can not initial');
        if (!validType) console.log('Type = ' + type + ', valid = page or snippet');

        if (!isTransLang) console.log('Language = ' + docLang + ', valid = not null + not equal `vi`');

        if (!sourceId) console.log('Translation for VI = ' + sourceId + ', valid = is a document ID');
    }

    function initTranslateDocumentButton()
    {
        var buttonText = 'Dịch các nội dung văn bản';

        e.detail.document.toolbar.add({
            xtype: "button",
            text: buttonText,
            iconCls: 'pimcore_icon_translations',
            scale: 'medium',
            handler: function (button) {
                pimcore.helpers.loadingShow();
                var requestOptions = {
                    method: 'POST',
                    headers: myHeaders
                };

                fetch('/admin/stf-trans/translator/document/' + id + '/' + docLang + '/' + sourceId, requestOptions)
                    .then(response => response.text())
                    .then(result => {
                        pimcore.helpers.loadingHide();
                        item.reload([])
                    })
                    .catch(error => {
                        pimcore.helpers.loadingHide();
                    });
            }.bind(this)
        });
    }
});

document.addEventListener(pimcore.events.postOpenObject, (e) => {
    var object = e.detail.object;
    var id = object.id;

    canTranslate();
    function canTranslate()
    {
        fetch('/admin/stf-trans/translator/object-can-translate/' + id, {
            method: 'POST',
            headers: myHeaders
        })
        .then(response => response)
        .then(result => {
            if (result.ok) {
                initTranslateObjectButton();
            }
        })
        .catch(error => {
        });
    }

    function initTranslateObjectButton()
    {
        var buttonText = 'Dịch tất cả';
        var buttons = [];

        var available_languages = pimcore.available_languages;
        var websiteLanguages = pimcore.settings.websiteLanguages;

        for (let i = 0; i < websiteLanguages.length; i++) {
            var lang = websiteLanguages[i];

            if (lang != 'vi') {
                var langButtonText = 'Dịch sang ' + available_languages[lang];
                var iconCls = "pimcore_icon_language_" + lang.toLowerCase();

                var buttonConfig = {
                    text: langButtonText,
                    iconCls: iconCls,
                    language: lang,
                    handler: function (button) {
                        pimcore.helpers.loadingShow();
                        var saveVersion = object.save('autoSave');
                        window.setTimeout(function() {

                            fetch('/admin/stf-trans/translator/object/' + id + '/' + button.config.language, requestOptions)
                                .then(response => response.text())
                                .then(result => {
                                    pimcore.helpers.loadingHide();
                                    object.reload([]);
                                })
                                .catch(error => {
                                    pimcore.helpers.loadingHide();
                                });
                        }, 2000);
                    }.bind(this)
                };

                buttons.push(buttonConfig);
            }
        }

        e.detail.object.toolbar.add({
            xtype: "splitbutton",
            text: buttonText,
            iconCls: 'pimcore_icon_translations',
            scale: 'medium',
            menu: [
                ...buttons
            ],
            handler: function (button) {
                pimcore.helpers.loadingShow();
                var saveVersion = object.save('autoSave');
                // window.setTimeout(function() {
                //     var requestOptions = {
                //         method: 'POST',
                //         headers: myHeaders
                //     };

                //     fetch('/admin/stf-trans/translator/object/' + id + '/all', requestOptions)
                //         .then(response => response.text())
                //         .then(result => {
                //             pimcore.helpers.loadingHide();
                //             object.reload([]);
                //         })
                //         .catch(error => {
                //             pimcore.helpers.loadingHide();
                //         });
                // }, 2000);

                window.setTimeout(function() {
                    var langNeedTransTotal = websiteLanguages.length - 1;
                    for (let i = 0; i < websiteLanguages.length; i++) {
                        var lang = websiteLanguages[i];

                        if (lang != 'vi') {
                            fetch('/admin/stf-trans/translator/object/' + id + '/' + lang, requestOptions)
                                .then(response => response.text())
                                .then(result => {
                                    langNeedTransTotal--;

                                    if (langNeedTransTotal == 0) {
                                        fetch('/admin/stf-trans/translator/object-merge/' + id , requestOptions)
                                            .then(response => response.text())
                                            .then(result => {
                                                pimcore.helpers.loadingHide();
                                                object.reload([]);
                                            })
                                            .catch(error => {
                                                pimcore.helpers.loadingHide();
                                                object.reload([]);
                                            });
                                    }
                                })
                                .catch(error => {
                                    langNeedTransTotal--;

                                    if (langNeedTransTotal == 0) {
                                        pimcore.helpers.loadingHide();
                                    }
                                });
                        }
                    }
                }, 2000);
            }.bind(this)
        });
    }
});

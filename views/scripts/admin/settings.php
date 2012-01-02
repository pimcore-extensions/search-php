<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Pimcore Search Plugin :: Settings</title>

<link href="/pimcore/static/js/lib/ext/resources/css/ext-all.css" media="screen" rel="Stylesheet" type="text/css"/>
<?php
   $conf = Zend_Registry::get("pimcore_config_system");

$themeUrl = "/pimcore/static/js/lib/ext/resources/css/xtheme-blue.css";
if ($conf->general->theme) {
    $themeUrl = $conf->general->theme;
}

?>
<link href="<?php echo  $themeUrl ?>" media="screen" rel="Stylesheet" type="text/css"/>
<script src="/pimcore/static/js/lib/ext/adapter/ext/ext-base.js" type="text/javascript"></script>

<script src="/pimcore/static/js/lib/ext/ext-all-debug.js" type="text/javascript"></script>

<script type="text/javascript" src="/pimcore/static/js/lib/ext-plugins/SuperBoxSelect/SuperBoxSelect.js"></script>

<link href="/pimcore/static/js/lib/ext-plugins/SuperBoxSelect/superboxselect.css" media="screen" rel="Stylesheet"
      type="text/css"/>
<link href="/plugins/SearchPhp/static/css/admin.css" media="screen" rel="Stylesheet" type="text/css"/>


<script type="text/javascript">




Ext.onReady(function() {

    Ext.QuickTips.init();

    var tagStore = new Ext.data.JsonStore({
        id:'url',
        root:'urls',
        fields:[

            {name:'url', type:'string'}
        ],
        url: '/plugin/SearchPhp/admin/get-frontend-urls'
    });
    var categoriesStore = new Ext.data.JsonStore({
        id:'category',
        root:'categories',
        fields:[
            {name:'category', type:'string'}
        ],
        url: '/plugin/SearchPhp/admin/get-frontend-categories'
    });
    var allowedStore = new Ext.data.JsonStore({
        id:'regex',
        root:'allowed',
        fields:[
            {name:'regex', type:'string'}
        ],
        url: '/plugin/SearchPhp/admin/get-frontend-allowed'
    });

    var forbiddenStore = new Ext.data.JsonStore({
        id:'regex',
        root:'forbidden',
        autoSave:false,
        autoDestroy:false,
        remoteSort: true,
        fields:[

            {name:'regex', type:'string'}
        ],
        url: '/plugin/SearchPhp/admin/get-frontend-forbidden'

    });


    var form1 = new Ext.form.FormPanel({
        id:'f1Form',
        renderTo: 'crawler_form',
        autoScroll: true,
        border:false,
        width: 600,
        autoHeight:true ,
        items:[
            {
                id : 'stateMessage',
                fieldLabel: '<?php echo   $this->translate->_("searchphp_status") ?>',
                xtype:'displayfield',  
                html: '<?php echo str_replace("------------------------------------------- ", "<br/>", SearchPhp_Plugin::getPluginState()); ?>'
            },
            {   xtype: 'buttongroup',
                fieldLabel: '<?php echo   $this->translate->_("searchphp_frontend_crawler") ?>',
                hideLabel: <?php echo  $this->config['search']['frontend']['enabled'] ? "false" : "true";?>,
                hidden: <?php echo  $this->config['search']['frontend']['enabled'] ? "false" : "true";?>,
                columns:2,
                bodyBorder:false,
                border: false,
                frame:false,  
                items: [{ 
                    xtype:'button', 
                    hideLabel: true,
                    text: '<?php echo   $this->translate->_("searchphp_start_crawler") ?>',
                    id: 'startFrontendCrawler',  
                    disabled: <?php if (SearchPhp_Plugin::frontendCrawlerRunning() or SearchPhp_Plugin::frontendCrawlerScheduledForStart() or !SearchPhp_Plugin::frontendConfigComplete()) { echo 'true'; } else echo 'false'; ?>,
                    listeners: {
                        click: function(button, event) {
                            Ext.Ajax.request({
                                url: "/plugin/SearchPhp/admin/start-frontend-crawler",
                                method: "get"
                            });
                            button.setDisabled(true);
                            Ext.Ajax.request({
                                url: "/plugin/SearchPhp/admin/get-state",
                                method: "get",
                                success: function (transport) {
                                    var res = transport.responseText.evalJSON();
                                    Ext.getCmp('stateMessage').setValue(res.message);
                                }
                            });

                        }
                    }

                },{
                    xtype:'button',
                    style: 'margin: 0 0 0 5px',  
                    hideLabel: true,
                    text: '<?php echo   $this->translate->_("searchphp_stop_crawler") ?>',
                    id: 'stopFrontendCrawler',
                    disabled: <?php if (!SearchPhp_Plugin::frontendCrawlerRunning()) { echo 'true'; } else echo 'false'; ?>,
                    listeners: {
                        click: function(button, event) {

                            searchPhpCrawlerLoadingMask = new Ext.LoadMask(Ext.get("f1Form"), {
                                id:"crawler-stop-mask",
                                msg:"<?php echo   $this->translate->_("searchphp_please_wait") ?>"
                            });

                            searchPhpCrawlerLoadingMask.show();

                            button.setDisabled(true);
                            Ext.Ajax.request({
                                url:"/plugin/SearchPhp/admin/stop-frontend-crawler",
                                method: "get",
                                success: function(transport){

                                    var res = Ext.decode(transport.responseText);
                                    if(res.success!=true){

                                        Ext.MessageBox.show({
                                            title: '<?php echo   $this->translate->_("SearchPhp_Frontend_Crawler_stop_failed") ?>',
                                            msg: '<?php echo   $this->translate->_("SearchPhp_Frontend_Crawler_stop_failed_description") ?>',
                                            buttons: Ext.Msg.OKCANCEL,
                                            icon: Ext.MessageBox.QUESTION,
                                            fn: function(v,s,o){
                                                if(o[0]=="ok"){
                                                    Ext.Ajax.request({
                                                        url: "/plugin/SearchPhp/admin/stop-frontend-crawler?force=true",
                                                        method: "get"
                                                    } );
                                                }
                                            }
                                        });
                                    } else {
                                        button.setDisabled(false);
                                        searchPhpCrawlerLoadingMask.hide();
                                    }
                                }
                            });

                            Ext.Ajax.request({
                                url: "/plugin/SearchPhp/admin/get-state",
                                method: "get",
                                success: function (transport) {
                                    var res = transport.responseText.evalJSON();
                                    Ext.getCmp('stateMessage').setValue(res.message);
                                    searchPhpCrawlerLoadingMask.hide();
                                }
                            });
                        }
                    }

                }]
            }

        ]
    });


    var form2 = new Ext.form.FormPanel({
        id:'f2Form',
        renderTo: 'settings_form',
        autoScroll: true,
        border:false,
        width: 600,
        autoHeight:true,
        buttons: [
            {
                text: '<?php echo $this->translate->_("searchphp_settings_save")?>',
                handler: function() {

                    Ext.Ajax.request({
                        url: "/plugin/SearchPhp/admin/set-config",
                        method: "post",
                        params: {data: Ext.encode(Ext.getCmp('f2Form').getForm().getFieldValues())},
                        success: function (transport) {
                            window.location.reload()
                        }
                    });
                }
            }
        ],
        items: [
            {xtype:'fieldset',
                id: 'basic_settings',
                title: '<?php echo  $this->translate->_('searchphp_basic') ?>',
                collapsible: false,
                autoHeight:true,
                labelWidth: 100,
                items :[
                    {
                        xtype:'displayfield',
                        value:'<?php echo  $this->translate->_('searchphp_frontend_enabled_description') ?>',
                        cls: 'description'
                    },
                    {
                        xtype:'checkbox',
                        autoHeight:true,
                        boxLabel: '<?php echo  $this->translate->_("searchphp_frontend_enabled")?>',
                        name: 'search.frontend.enabled',
                        checked: <?php echo  $this->config['search']['frontend']['enabled'] ? "true" : "false";?>,
                        inputValue: '1',
                        ctCls: "x-form-item",
                        listeners:{
                            check: function(checkbox, checked) {
                                if (checked) {
                                    Ext.getCmp('frontend_settings').show();
                                } else {
                                    Ext.getCmp('frontend_settings').hide();

                                }
                            }
                        }
                    }
                ]
            },
            {xtype:'fieldset',
                id: 'frontend_settings',
                title: '<?php echo  $this->translate->_('searchphp_frontend_settings') ?>',
                collapsible: false,
                autoHeight:true,
                labelWidth: 100,
                hidden: <?php echo  $this->config['search']['frontend']['enabled'] ? "false" : "true";?>,
                defaultType: 'superboxselect',
                defaults: {
                    allowBlank:true,
                    msgTarget: 'under',
                    allowAddNewData: true,
                    queryDelay: 0,
                    triggerAction: 'all',
                    extraItemCls: 'x-tag',
                    resizable: true,
                    mode: 'remote',
                    anchor:'100%',
                    queryValuesDelimiter:'__#--#__',
                    minChars: 2

                },
                items :[
                    {
                        xtype:'displayfield',
                        value:'<?php echo  $this->translate->_('searchphp_frontend_ignoreLanguage_description') ?>',
                        cls: 'description'
                    },
                    {
                        xtype:'checkbox',
                        fieldLabel: '<?php echo $this->translate->_('language')?>',

                        autoHeight:true,
                        boxLabel: '<?php echo  $this->translate->_("searchphp_frontend_ignoreLanguage")?>',
                        name: 'search.frontend.ignoreLanguage',
                        checked: <?php echo  $this->config['search']['frontend']['ignoreLanguage'] ? "true" : "false";?>,
                        inputValue: '1'
                    },
                    {
                        xtype:'displayfield',
                        value:'<?php echo  $this->translate->_('searchphp_frontend_fuzzySearch_description') ?>',
                        cls: 'description'
                    },
                    {
                        xtype:'checkbox',
                        fieldLabel: '<?php echo $this->translate->_('searchphp_frontend_fuzzySearch')?>',

                        autoHeight:true,
                        boxLabel: '<?php echo  $this->translate->_("searchphp_search_suggestions")?>',
                        name: 'search.frontend.fuzzySearch',
                        checked: <?php echo  $this->config['search']['frontend']['fuzzySearch'] ? "true" : "false";?>,
                        inputValue: '1'
                    },
                    {
                        xtype:'displayfield',
                        value:'<?php echo  $this->translate->_('searchphp_frontend_ownHostOnly_description') ?>',
                        cls: 'description'
                    },
                    {
                        xtype:'checkbox',
                        fieldLabel: '<?php echo $this->translate->_('subdomains')?>',

                        autoHeight:true,
                        boxLabel: '<?php echo  $this->translate->_("searchphp_frontend_ownHostOnly")?>',
                        name: 'search.frontend.ownHostOnly',
                        checked: <?php echo  $this->config['search']['frontend']['ownHostOnly'] ? "true" : "false";?>,
                        inputValue: '1'
                    },
                    {
                        xtype:'displayfield',
                        value:'<?php echo  $this->translate->_('searchphp_frontend_crawler_maxThreads_description') ?>',
                        cls: 'description'
                    },
                    {
                        xtype:'field',
                        fieldLabel: '<?php echo $this->translate->_("searchphp_frontend_crawler_maxThreads")?>',
                        name: 'search.frontend.crawler.maxThreads',
                        collapsible: false,
                        autoHeight:true,
                        value:'<?php echo $this->config['search']['frontend']['crawler']['maxThreads'] ?>'

                    },
                    {
                        xtype:'displayfield',
                        value:'<?php echo  $this->translate->_('searchphp_frontend_crawler_maxLinkDepth_description') ?>',
                        cls: 'description'
                    },
                    {
                        xtype:'field',
                        fieldLabel: '<?php echo $this->translate->_("searchphp_frontend_crawler_maxLinkDepth")?>',
                        name: 'search.frontend.crawler.maxLinkDepth',
                        collapsible: false,
                        autoHeight:true,
                        value:'<?php echo $this->config['search']['frontend']['crawler']['maxLinkDepth'] ?>'

                    },
                    {
                        xtype:'displayfield',
                        value:'<?php echo  $this->translate->_('searchphp_frontend_content_indicator_description') ?>',
                        cls: 'description'
                    },
                    {
                        xtype:'field',
                        fieldLabel: '<?php echo $this->translate->_("searchphp_frontend_content_start_indicator")?>',
                        name: 'search.frontend.crawler.contentStartIndicator',
                        collapsible: false,
                        autoHeight:true,
                        value:'<?php echo $this->config['search']['frontend']['crawler']['contentStartIndicator'] ?>'

                    },

                    {
                        xtype:'field',
                        fieldLabel: '<?php echo $this->translate->_("searchphp_frontend_content_end_indicator")?>',
                        name: 'search.frontend.crawler.contentEndIndicator',
                        collapsible: false,
                        autoHeight:true,
                        value:'<?php echo $this->config['search']['frontend']['crawler']['contentEndIndicator'] ?>'
                    },
                    {
                        xtype:'displayfield',
                        value:'<?php echo  $this->translate->_('searchphp_frontend_categories_description') ?>',
                        cls: 'description'
                    },
                    {

                        fieldLabel: '<?php echo  $this->translate->_("searchphp_frontend_categories")?>',
                        emptyText: '<?php echo $this->translate->_("searchphp_frontend_categories_empty_text")?>',
                        name: 'search.frontend.categories',
                        value: <?php echo Zend_Json::encode($this->config['search']['frontend']['categories']) ?>,
                        store: categoriesStore,
                        ctCls: 'searchphp',
                        displayField: 'category',
                        valueField: 'category',
                        listeners: {
                            newitem: function(bs, v, f) {
                                v = v + '';
                                var newObj = {
                                    category: v
                                };
                                bs.addNewItem(newObj);
                            }
                        }
                    },
                    {
                        xtype:'displayfield',
                        value:'<?php echo  $this->translate->_('searchphp_frontend_settings_urls_description') ?>',
                        cls: 'description'
                    },
                    {

                        fieldLabel: '<?php echo  $this->translate->_("searchphp_frontend_settings_urls")?>'+' *',
                        emptyText: '<?php echo $this->translate->_("searchphp_frontend_settings_empty_text")?>',
                        name: 'search.frontend.urls',
                        value: <?php echo Zend_Json::encode($this->config['search']['frontend']['urls']) ?>,
                        store: tagStore,
                        displayField: 'url',
                        ctCls: 'searchphp',
                        valueField: 'url',
                        listeners: {
                            newitem: function(bs, v, f) {
                                v = v + '';
                                var newObj = {
                                    url: v
                                };
                                bs.addNewItem(newObj);
                            }
                        }
                    },
                    {
                        xtype:'displayfield',
                        value:'<?php echo  $this->translate->_('searchphp_frontend_allowed_description') ?>',
                        cls: 'description'
                    },
                    {

                        fieldLabel: '<?php echo $this->translate->_("searchphp_frontend_allowed")?>'+' *',
                        emptyText: '<?php echo $this->translate->_("searchphp_frontend_allowed_empty_text")?>',
                        name: 'search.frontend.validLinkRegexes',
                        store: allowedStore,
                        displayField: 'regex',
                        ctCls: 'searchphp',
                        valueField: 'regex',
                        value: <?php echo Zend_Json::encode($this->config['search']['frontend']['validLinkRegexes']) ?>,
                        listeners: {
                            newitem: function(bs, v, f) {
                                v = v + '';
                                var newObj = {
                                    regex: v
                                };
                                bs.addNewItem(newObj);
                            }
                        }
                    },
                    {
                        xtype:'displayfield',
                        value:'<?php echo  $this->translate->_('searchphp_frontend_forbidden_description') ?>',
                        cls: 'description'
                    },
                    {
                        value: <?php echo Zend_Json::encode($this->config['search']['frontend']['invalidLinkRegexesEditable']) ?>,
                        fieldLabel: '<?php echo $this->translate->_("searchphp_frontend_forbidden")?>',
                        emptyText: '<?php echo $this->translate->_("searchphp_frontend_forbidden_empty_text")?>',
                        name: 'search.frontend.invalidLinkRegexesEditable',
                        ctCls: 'searchphp',
                        store: forbiddenStore,
                        displayField: 'regex',
                        valueField: 'regex',
                        extraItemCls: 'x-tag',
                        listeners: {
                            newitem: function(bs, v, f) {
                                v = v + '';
                                var newObj = {
                                    regex: v
                                };
                                bs.addNewItem(newObj);
                            }
                        }
                    },
                     {
                        xtype:'displayfield',
                        value:'*) '+'<?php echo  $this->translate->_('searchphp_frontend_mandatory_fields') ?>',
                        cls: 'mandatory_hint'
                    }
                ]}
          
        ]
    });

    window.setInterval(function () {
        Ext.Ajax.request({
            url: "/plugin/SearchPhp/admin/get-state",
            method: "get",
            success: function (response) {
                var res = Ext.decode(response.responseText);
                Ext.getCmp('stateMessage').setValue(res.message);
                Ext.getCmp("startFrontendCrawler").setDisabled(res.frontendButtonDisabled);
                Ext.getCmp("stopFrontendCrawler").setDisabled(res.frontendStopButtonDisabled);
                Ext.getCmp("f1Form").doLayout();

            }
        });
    }, 10000);


});


</script>

</head>
<body>
<div id="page">
    <div id="crawler_form" class="exForm"></div>

    <div id="settings_form" class="exForm"></div>


</div>
</body>
</html>


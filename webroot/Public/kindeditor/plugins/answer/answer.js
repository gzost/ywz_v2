KindEditor.plugin('answer', function(K) {
        var editor = this, name = 'answer';
        //点击图标时执行
        editor.clickToolbar(name, function() {
                editor.insertHtml('<p><input type="text" name[]="answer" /></p>');
        });
});
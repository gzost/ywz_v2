KindEditor.plugin('answer', function(K) {
    var editor = this, name = 'answer';
    //点击图标时执行
    editor.clickToolbar(name, function() {
        editor.insertHtml("<span class='answerArea'> 题目序号:<span class='questiondId'>()</span>" +
            "  答案类型:<span class='questiondType'>()</span> </span>");
    });
});
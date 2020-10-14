//修改类型改变必选的值加上 *
function addRed(domNodeStrArr) {
    // var innerValue= $('#'+domNode).parent().prev().innerHTML;
    for (var index in domNodeStrArr) {
        $('#' + domNodeStrArr[index]).parent().prev().prepend("<span class='c-red'> * </span>")
    }
}

// 移除red
function removeRed(domNodeStrArr){
    for (var index in domNodeStrArr) {
        $('#' + domNodeStrArr[index]).parent().prev().find('.c-red').remove();
    }
}

// 更改加密方式变化
function fun(a, b, c, d,fileupload=false) {
    var divKey = document.getElementById(a);
    var divFile = document.getElementById(b);
    var form = document.getElementById(c);
    if (d == "1") {
        divKey.style.display = "block";
        divFile.style.display = "none";
        form.enctype = "application/x-www-form-urlencoded";
    } else {
        divKey.style.display = "none";
        divFile.style.display = "block";
        form.enctype = "multipart/form-data";
    }
    if(fileupload){
        form.enctype = "multipart/form-data";
    }
}

//检查必填选项
function checkRequired() {
    //如果是
    var encryption = $("input[name='pm_encryptType']:checked").val();
    var cRed;
    if(encryption==1){  //  不需要检查 秘钥文件字段
        cRed = $(".c-red:not(#private_key_file_span)");
    }else{   //不需要检测商户秘钥字段
        cRed = $(".c-red:not(#hmacVal_span)");
    }
    for (var i = 0; i < cRed.length; i++) {
        // console.log($(cRed[i]).parent().next().find('input').val(),'-',)
        if ($(cRed[i]).parent().next().find('input').val() == '') {
            alert('带*参数不能为空');
            return false;
        }
    }
    return true;
}
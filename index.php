<?php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>PHP Translate Content Parset - Test</title>
    <script src='https://cdn.tinymce.com/4/tinymce.min.js' referrerpolicy="origin"></script>
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <link href="https://fonts.googleapis.com/css?family=Lato:400,700,900&display=swap" rel="stylesheet">
</head>
<body style="font-family: 'Lato', sans-serif; box-sizing: border-box; margin: 0;">
    <form method="post">
        <textarea id="rich-text-editor" rows="15"></textarea>
    </form>
    <button id="submit-translate" style="width: 100%; height: 40px;">Translate</button>
    <div id="original-preview" style="width: 47%; background: #f1f1f1; margin: 15px 6% 0 0; float: left;">
        <div style="padding: 15px;">
            <h3 style="margin: 0 0 10px 0;">Original content</h3>
            <div class="content-html" style="word-break: break-all;"></div>
        </div>
    </div>
    <div id="translated-preview" style="width: 47%; background: #f1f1f1; margin: 15px 0; float: left;">
        <div style="padding: 15px;">
            <h3 style="margin: 0 0 10px 0;">Translated content</h3>
            <div class="content-html"></div>
        </div>
    </div>
</body>

<script>
    $(document).ready(function() {
        tinymce.init({
            selector: '#rich-text-editor',
            setup: function(ed) {
                ed.on('keyup', function(e) {
                    var originalHTML = ed.getContent();
                    $('#original-preview .content-html').html(originalHTML);
                });
            },
            plugins: [
                "advlist autolink lists link image charmap print preview anchor",
                "searchreplace visualblocks code fullscreen",
                "insertdatetime media table paste"
            ],
            toolbar: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image"
        });

        $('#submit-translate').click(function() {
            var content = tinymce.get('rich-text-editor').getContent();
            console.log(content);
            $.post("source.php", {d: content}, function(data) {
                console.log(data);
                $('#translated-preview .content-html').html(data);
            });
        });
    });
</script>
</html>
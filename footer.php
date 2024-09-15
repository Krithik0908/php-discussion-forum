<script>
    function showAskModal() {
        document.getElementById('askModal').style.display = 'block';
    }
    function hideAskModal() {
        document.getElementById('askModal').style.display = 'none';
    }
    function showReplyForm(answerId) {
        const form = document.getElementById('replyForm-' + answerId);
        if (form.style.display === 'none') {
            form.style.display = 'block';
        } else {
            form.style.display = 'none';
        }
    }

    window.onclick = function(event) {
        const modal = document.getElementById('askModal');
        if (event.target === modal) {
            hideAskModal();
        }
    }


</script>
</body>
</html>

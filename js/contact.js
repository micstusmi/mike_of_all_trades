$(document).ready(function() {
    $('#contactForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            type: 'POST',
            url: 'process-contact.php',
            data: $(this).serialize(),
            success: function(response) {
                $('#formResponse').html('<div class="alert alert-success">Message Sent!</div>');
                $('#contactForm')[0].reset();
            }
        });
    });
});
<footer class="footer-section bg-white border-top py-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <strong>Mike Of All Trades</strong><br>
            <small class="text-muted">&copy; <?php echo date("Y"); ?> | Victoria, Australia</small>
        </div>
        <div>
            <i class="bi bi-instagram me-3 text-muted"></i>
            <i class="bi bi-linkedin me-3 text-muted"></i>
            <i class="bi bi-envelope text-muted"></i>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.getElementById('skillSearch').addEventListener('keyup', function(e) {
    let query = e.target.value;
    if (query.length > 2) {
        fetch('search_logic.php?query=' + query)
            .then(res => res.text())
            .then(data => console.log("Search results:", data));
    }
});
</script>

</body>
</html>
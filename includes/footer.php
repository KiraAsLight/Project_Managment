<!-- Footer -->
<footer class="text-center text-gray-500 text-sm py-4">
    <p>© 2025 PT. Wiratama Globalindo Jaya · Project Management Dashboard</p>
</footer>

<script>
    // Auto refresh setiap 5 menit
    setTimeout(function() {
        location.reload();
    }, 600000);

    // Animasi smooth untuk circular progress
    window.addEventListener('load', function() {
        const circles = document.querySelectorAll('circle[stroke-dashoffset]');
        circles.forEach(circle => {
            const offset = circle.getAttribute('stroke-dashoffset');
            circle.style.strokeDashoffset = offset;
        });
    });
</script>
</body>

</html>
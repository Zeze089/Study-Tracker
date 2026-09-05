<script>
    (function () {
        let storedTheme = null;

        try {
            storedTheme = localStorage.getItem('study-tracker-theme');
        } catch (error) {
            storedTheme = null;
        }

        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const shouldUseDark = storedTheme === 'dark' || (!storedTheme && prefersDark);

        document.documentElement.classList.toggle('dark', shouldUseDark);
        document.documentElement.style.colorScheme = shouldUseDark ? 'dark' : 'light';
    })();
</script>

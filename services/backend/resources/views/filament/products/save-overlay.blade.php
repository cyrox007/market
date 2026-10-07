<script>
    // Пелена на время «Сохранить и остаться»: скрывает перестройку формы и вкладок
    (() => {
        if (window.__productSaveOverlay) return;
        window.__productSaveOverlay = true;

        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:40;display:flex;align-items:center;justify-content:center;'
            + 'background:rgba(229,231,235,.9);opacity:0;pointer-events:none;transition:opacity .15s ease';
        overlay.innerHTML = '<div style="width:40px;height:40px;border-radius:9999px;border:4px solid rgba(156,163,175,.5);'
            + 'border-top-color:var(--primary-600);animation:product-save-spin .8s linear infinite"></div>';
        const style = document.createElement('style');
        style.textContent = '@keyframes product-save-spin{to{transform:rotate(360deg)}}';
        document.head.appendChild(style);
        document.body.appendChild(overlay);

        let pending = false;
        const show = () => { pending = true; overlay.style.pointerEvents = 'auto'; overlay.style.opacity = '1'; };
        // Снимаем после двух кадров: к этому моменту Livewire уже перестроил страницу
        const hide = () => requestAnimationFrame(() => requestAnimationFrame(() => setTimeout(() => {
            pending = false; overlay.style.opacity = '0'; overlay.style.pointerEvents = 'none';
        }, 150)));

        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-save-overlay]')) show();
        }, true);

        const register = () => Livewire.hook('commit', ({ succeed, fail }) => {
            if (!pending) return;
            succeed(hide);
            fail(hide);
        });

        window.Livewire ? register() : document.addEventListener('livewire:init', register);
    })();
</script>

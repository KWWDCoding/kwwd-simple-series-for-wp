(function() {
    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('.kwwd-series-card-expand');
        if (!trigger) return;
        e.preventDefault();
        var list = document.getElementById(trigger.getAttribute('data-target'));
        if (!list) return;
        var hidden = (list.style.display === 'none');
        list.style.display = hidden ? 'block' : 'none';
        trigger.textContent = hidden ? 'Hide posts' : 'View posts';
    });
})();

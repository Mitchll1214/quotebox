(function () {
    'use strict';

    var body = document.body;
    var menuToggle = document.getElementById('menuToggle');
    var backdrop = document.getElementById('sidebarBackdrop');

    function closeNav() {
        body.classList.remove('nav-open');
        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'false');
        }
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', function () {
            var open = body.classList.toggle('nav-open');
            menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeNav);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeNav();
        }
    });

    // Confirmation for any form or submit button carrying data-confirm.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var submitter = event.submitter;
        var message = (submitter && submitter.getAttribute('data-confirm')) || form.getAttribute('data-confirm');

        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    var selectAll = document.getElementById('selectAll');
    var rowChecks = Array.prototype.slice.call(document.querySelectorAll('.row-check'));
    var selectedCount = document.getElementById('selectedCount');

    function updateSelection() {
        var selected = rowChecks.filter(function (checkbox) {
            return checkbox.checked;
        }).length;

        if (selectedCount) {
            selectedCount.textContent = '已选 ' + selected + ' 条';
        }

        if (selectAll) {
            selectAll.checked = rowChecks.length > 0 && selected === rowChecks.length;
            selectAll.indeterminate = selected > 0 && selected < rowChecks.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowChecks.forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
            updateSelection();
        });
    }

    rowChecks.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateSelection);
    });

    if (rowChecks.length) {
        updateSelection();
    }

    function legacyCopy(text) {
        var area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', 'readonly');
        area.style.position = 'fixed';
        area.style.top = '-1000px';
        document.body.appendChild(area);
        area.select();
        var ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (error) {
            ok = false;
        }
        document.body.removeChild(area);
        return ok;
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }

        var button = target.closest('[data-copy]');
        if (!button) {
            return;
        }

        var text = button.getAttribute('data-copy');
        var original = button.textContent;

        function markCopied() {
            button.textContent = '已复制';
            window.setTimeout(function () {
                button.textContent = original;
            }, 1600);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(markCopied).catch(function () {
                if (legacyCopy(text)) {
                    markCopied();
                }
            });
        } else if (legacyCopy(text)) {
            markCopied();
        }
    });
}());

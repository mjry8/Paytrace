// Tiny helpers. The site works without JavaScript; this only prevents double-clicking "pay".
document.querySelectorAll('form').forEach(function (form) {
  form.addEventListener('submit', function () {
    var button = form.querySelector('button');
    if (button) { setTimeout(function () { button.disabled = true; }, 0); }
  });
});

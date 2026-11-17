// 1. Tab login <-> register pada satu halaman
document.querySelectorAll('[data-tab]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var tab = btn.dataset.tab;
    document.querySelectorAll('[data-tab]').forEach(function (b) { b.classList.toggle('active', b === btn); });
    document.querySelector('#form-login').classList.toggle('d-none', tab !== 'login');
    document.querySelector('#form-register').classList.toggle('d-none', tab !== 'register');
  });
});
// 2. Tampilkan / sembunyikan password
document.querySelectorAll('[data-toggle-pw]').forEach(function (cb) {
  cb.addEventListener('change', function () {
    cb.closest('form').querySelectorAll('input[type=password],input[data-pw]').forEach(function (i) {
      i.type = cb.checked ? 'text' : 'password'; i.dataset.pw = 1;
    });
  });
});
// 3. Validasi form sisi klien (validasi sebenarnya tetap di server)
document.querySelectorAll('form[data-validate]').forEach(function (form) {
  form.addEventListener('submit', function (e) {
    var ok = true;
    form.querySelectorAll('[required]').forEach(function (el) {
      var fb = el.parentElement.querySelector('.invalid-feedback');
      if (!fb) { fb = document.createElement('div'); fb.className = 'invalid-feedback'; el.parentElement.appendChild(fb); }
      if (el.value.trim() === '') { el.classList.add('is-invalid'); fb.textContent = 'Kolom ini wajib diisi.'; ok = false; }
    });
    var p1 = form.querySelector('[name=password]'), p2 = form.querySelector('[name=konfirmasi]');
    if (ok && p1 && p2 && p1.value !== p2.value) { p2.classList.add('is-invalid'); p2.parentElement.querySelector('.invalid-feedback').textContent = 'Konfirmasi password tidak sama.'; ok = false; }
    if (!ok) e.preventDefault();
  });
  form.addEventListener('input', function (e) { e.target.classList.remove('is-invalid'); });
});
// 4. Konfirmasi sebelum hapus
document.querySelectorAll('form[data-confirm]').forEach(function (f) {
  f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
});
// 5. Filter tabel secara langsung
var cari = document.querySelector('#cari');
if (cari) cari.addEventListener('input', function () {
  var k = cari.value.toLowerCase();
  document.querySelectorAll('#tabel-cari tbody tr').forEach(function (tr) { tr.style.display = tr.textContent.toLowerCase().includes(k) ? '' : 'none'; });
});
// 6. Pratinjau foto sebelum diunggah
var fi = document.querySelector('#input-foto');
if (fi) fi.addEventListener('change', function () {
  var p = document.querySelector('#pratinjau');
  if (fi.files[0] && p) { p.src = URL.createObjectURL(fi.files[0]); p.classList.remove('d-none'); }
});

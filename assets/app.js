"use strict";
// Skrip kecil untuk semua halaman (JavaScript biasa).
// 1. Login <-> Daftar pada satu halaman (dua tampilan, bukan dua file)
const tombolTab = document.querySelectorAll("[data-tab]");
const tampilanMasuk = document.querySelector("#form-login");
const tampilanDaftar = document.querySelector("#form-register");

tombolTab.forEach(function (tombol) {
  tombol.addEventListener("click", function () {
    const daftar = tombol.dataset.tab === "register";
    tampilanMasuk.classList.toggle("hidden", daftar);
    tampilanDaftar.classList.toggle("hidden", !daftar);
  });
});

// 2. Ikon mata: tampilkan / sembunyikan password
document.querySelectorAll("[data-mata]").forEach(function (tombol) {
  tombol.addEventListener("click", function () {
    const kolom = tombol.parentElement.querySelector("input");
    const tampil = kolom.type === "password";
    kolom.type = tampil ? "text" : "password";
    tombol.firstElementChild.classList.toggle("hidden", tampil);
    tombol.lastElementChild.classList.toggle("hidden", !tampil);
  });
});

// 3. Validasi form di sisi klien (validasi yang mengikat tetap di server)
function tampilkanError(kolom, teks) {
    kolom.classList.add("border-danger-600");
    const induk = kolom.closest(".mb-3") || kolom.parentElement;
    if (!induk) {
        return;
    }
    let pesan = induk.querySelector(".pesan-error");
    if (!pesan) {
        pesan = document.createElement("p");
        pesan.className = "pesan-error mt-1 text-xs text-danger-700";
        induk.appendChild(pesan);
    }
    pesan.textContent = teks;
}
document.querySelectorAll("form[data-validasi]").forEach(function (form) {
    form.addEventListener("submit", function (e) {
        let valid = true;
        form.querySelectorAll("[required]").forEach(function (kolom) {
            if (kolom.value.trim() === "") {
                tampilkanError(kolom, "Kolom ini wajib diisi.");
                valid = false;
            }
        });
        const sandi = form.querySelector("[name=password]");
        const ulang = form.querySelector("[name=konfirmasi]");
        if (valid && sandi && ulang && sandi.value !== ulang.value) {
            tampilkanError(ulang, "Konfirmasi password tidak sama.");
            valid = false;
        }
        if (!valid) {
            e.preventDefault();
        }
    });
    // Hapus tanda error begitu pengguna mulai mengetik lagi
    form.addEventListener("input", function (e) {
        const kolom = e.target;
        kolom.classList.remove("border-danger-600");
        const pembungkus = kolom.closest(".mb-3") || kolom.parentElement;
        const pesan = pembungkus ? pembungkus.querySelector(".pesan-error") : null;
        if (pesan) {
            pesan.textContent = "";
        }
    });
});
// 4. Konfirmasi sebelum menghapus
document.querySelectorAll("form[data-konfirmasi]").forEach(function (form) {
    form.addEventListener("submit", function (e) {
        if (!confirm(form.dataset.konfirmasi)) {
            e.preventDefault();
        }
    });
});
// 5. Cari di tabel secara langsung
const kolomCari = document.querySelector("#cari");
if (kolomCari) {
    kolomCari.addEventListener("input", function () {
        const kata = kolomCari.value.toLowerCase();
        document.querySelectorAll("#tabel-cari tbody tr").forEach(function (baris) {
            const cocok = (baris.textContent || "").toLowerCase().includes(kata);
            baris.style.display = cocok ? "" : "none";
        });
    });
}
// 6. Foto profil: pilih file -> potong persegi (geser + zoom) -> pratinjau
const inputFoto = document.querySelector("#input-foto");
const pratinjau = document.querySelector("#pratinjau");
const modalCrop = document.querySelector("#modal-crop");

if (inputFoto && pratinjau && modalCrop) {
  const kanvas = document.querySelector("#kanvas-crop");
  const geserZoom = document.querySelector("#zoom-crop");
  const konteks = kanvas.getContext("2d");
  const UKURAN = kanvas.width; // sisi area potong (piksel)
  const HASIL = 400;           // sisi foto hasil (piksel)

  let gambar = null;           // gambar yang sedang dipotong
  let zoom = 1;                // 1 = pas menutupi area potong
  let kiri = 0;                // posisi pojok kiri gambar di area potong
  let atas = 0;                // posisi pojok atas gambar di area potong
  let seret = null;            // data saat foto sedang digeser

  // Skala gambar: zoom 1 membuat sisi terpendek gambar pas dengan area potong
  function skala() {
    const dasar = Math.max(UKURAN / gambar.naturalWidth, UKURAN / gambar.naturalHeight);
    return dasar * zoom;
  }

  // Gambar tidak boleh bergeser sampai meninggalkan ruang kosong
  function batasi() {
    const lebar = gambar.naturalWidth * skala();
    const tinggi = gambar.naturalHeight * skala();
    kiri = Math.min(0, Math.max(UKURAN - lebar, kiri));
    atas = Math.min(0, Math.max(UKURAN - tinggi, atas));
  }

  function gambarUlang() {
    const s = skala();
    konteks.clearRect(0, 0, UKURAN, UKURAN);
    konteks.drawImage(gambar, kiri, atas, gambar.naturalWidth * s, gambar.naturalHeight * s);
    // Redupkan bagian di luar lingkaran
    konteks.fillStyle = "rgba(28, 25, 23, 0.55)";
    konteks.beginPath();
    konteks.rect(0, 0, UKURAN, UKURAN);
    konteks.arc(UKURAN / 2, UKURAN / 2, UKURAN / 2 - 2, 0, Math.PI * 2, true);
    konteks.fill("evenodd");
    konteks.strokeStyle = "#ffffff";
    konteks.lineWidth = 2;
    konteks.beginPath();
    konteks.arc(UKURAN / 2, UKURAN / 2, UKURAN / 2 - 2, 0, Math.PI * 2);
    konteks.stroke();
  }

  function bukaModal() {
    modalCrop.classList.remove("hidden");
    modalCrop.classList.add("flex");
  }

  function tutupModal() {
    modalCrop.classList.add("hidden");
    modalCrop.classList.remove("flex");
  }

  function batalkan() {
    inputFoto.value = "";
    pratinjau.classList.add("hidden");
    tutupModal();
  }

  // 1) Pengguna memilih file
  inputFoto.addEventListener("change", function () {
    const berkas = inputFoto.files[0];
    if (!berkas) {
      return;
    }
    const img = new Image();
    img.onload = function () {
      gambar = img;
      zoom = 1;
      geserZoom.value = 1;
      kiri = (UKURAN - img.naturalWidth * skala()) / 2; // mulai di tengah
      atas = (UKURAN - img.naturalHeight * skala()) / 2;
      gambarUlang();
      bukaModal();
    };
    img.onerror = function () {
      alert("File ini tidak bisa dibaca sebagai gambar. Pilih file JPG atau PNG.");
      inputFoto.value = "";
    };
    img.src = URL.createObjectURL(berkas);
  });

  // 2) Memperbesar: titik di tengah area potong tetap di tempatnya
  geserZoom.addEventListener("input", function () {
    const skalaLama = skala();
    const titikX = (UKURAN / 2 - kiri) / skalaLama;
    const titikY = (UKURAN / 2 - atas) / skalaLama;
    zoom = Number(geserZoom.value);
    kiri = UKURAN / 2 - titikX * skala();
    atas = UKURAN / 2 - titikY * skala();
    batasi();
    gambarUlang();
  });

  // 3) Menggeser foto dengan mouse atau sentuhan
  kanvas.addEventListener("pointerdown", function (e) {
    seret = { x: e.clientX, y: e.clientY, kiri: kiri, atas: atas };
    kanvas.setPointerCapture(e.pointerId);
  });
  kanvas.addEventListener("pointermove", function (e) {
    if (!seret) {
      return;
    }
    const rasio = UKURAN / kanvas.getBoundingClientRect().width; // jika kanvas tampil lebih kecil dari ukuran aslinya
    kiri = seret.kiri + (e.clientX - seret.x) * rasio;
    atas = seret.atas + (e.clientY - seret.y) * rasio;
    batasi();
    gambarUlang();
  });
  kanvas.addEventListener("pointerup", function () { seret = null; });
  kanvas.addEventListener("pointercancel", function () { seret = null; });

  // 4) Pakai foto: potong sesuai area, simpan sebagai JPG persegi, taruh di kolom file
  document.querySelector("#crop-pakai").addEventListener("click", function () {
    const hasil = document.createElement("canvas");
    hasil.width = HASIL;
    hasil.height = HASIL;
    const pena = hasil.getContext("2d");
    pena.fillStyle = "#ffffff"; // latar putih untuk PNG transparan
    pena.fillRect(0, 0, HASIL, HASIL);
    const s = skala();
    pena.drawImage(gambar, -kiri / s, -atas / s, UKURAN / s, UKURAN / s, 0, 0, HASIL, HASIL);

    hasil.toBlob(function (blob) {
      const berkas = new File([blob], "foto-profil.jpg", { type: "image/jpeg" });
      const daftarBerkas = new DataTransfer();
      daftarBerkas.items.add(berkas);
      inputFoto.files = daftarBerkas.files;
      pratinjau.src = URL.createObjectURL(blob);
      pratinjau.classList.remove("hidden");
      tutupModal();
    }, "image/jpeg", 0.9);
  });

  document.querySelector("#crop-batal").addEventListener("click", batalkan);
  modalCrop.addEventListener("click", function (e) {
    if (e.target === modalCrop) {
      batalkan();
    }
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && modalCrop.classList.contains("flex")) {
      batalkan();
    }
  });
}

// 7. Sidebar
//    - Layar lebar: tombol panel menciutkan sidebar menjadi hanya ikon (animasi lebar, diingat di browser)
//    - HP: sidebar berupa laci yang meluncur dari kiri, dengan lapisan gelap di belakangnya
const halaman = document.documentElement;
const sidebar = document.querySelector("#sidebar");
const lapisanMenu = document.querySelector("#lapisan-menu");
const tombolCiut = document.querySelector("#tombol-sidebar");
const tombolBukaMenu = document.querySelector("#buka-menu");
const tombolTutupMenu = document.querySelector("#tutup-menu");

function bukaLaci() {
  sidebar.classList.remove("-translate-x-full");
  lapisanMenu.classList.remove("opacity-0", "pointer-events-none");
  lapisanMenu.classList.add("opacity-100");
  document.body.classList.add("overflow-hidden"); // halaman di belakang tidak ikut tergulung
  tombolBukaMenu.setAttribute("aria-expanded", "true");
  tombolTutupMenu.focus();
}

function tutupLaci() {
  sidebar.classList.add("-translate-x-full");
  lapisanMenu.classList.add("opacity-0", "pointer-events-none");
  lapisanMenu.classList.remove("opacity-100");
  document.body.classList.remove("overflow-hidden");
  tombolBukaMenu.setAttribute("aria-expanded", "false");
}

if (sidebar && lapisanMenu && tombolBukaMenu && tombolTutupMenu) {
  tombolBukaMenu.addEventListener("click", bukaLaci);
  tombolTutupMenu.addEventListener("click", tutupLaci);
  lapisanMenu.addEventListener("click", tutupLaci);
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      tutupLaci();
    }
  });
  // Jika layar diputar / diperlebar sampai ukuran desktop, pastikan laci tertutup
  window.addEventListener("resize", function () {
    if (window.matchMedia("(min-width: 768px)").matches) {
      tutupLaci();
    }
  });
}

if (tombolCiut) {
  tombolCiut.addEventListener("click", function () {
    const tutup = halaman.dataset.sidebar !== "tutup";
    halaman.dataset.sidebar = tutup ? "tutup" : "buka";
    try {
      localStorage.setItem("sidebar", tutup ? "tutup" : "buka");
    } catch (error) {
      // penyimpanan browser tidak tersedia: abaikan
    }
  });
}

// 8. Kolom nomor: blok huruf e, tanda + - , dan titik
document.querySelectorAll("input[data-nomor]").forEach(function (kolom) {
  kolom.addEventListener("keydown", function (e) {
    if (["e", "E", "+", "-", ".", ","].includes(e.key)) {
      e.preventDefault();
    }
  });
});

// 9. Kolom NIP: hanya angka (huruf, spasi, dan simbol dibuang saat mengetik atau menempel).
// Tipe kolom tetap teks supaya nilainya dikirim & disimpan sebagai string (angka 0 di depan aman).
document.querySelectorAll("input[data-angka]").forEach(function (kolom) {
  kolom.addEventListener("input", function () {
    const bersih = kolom.value.replace(/\D/g, "");
    if (bersih !== kolom.value) {
      kolom.value = bersih;
    }
  });
});

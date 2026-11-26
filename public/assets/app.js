"use strict";

function aktifkanTabLogin() {
  const tombolTab = document.querySelectorAll("[data-tab]");
  const tampilanMasuk = document.querySelector("#form-login");
  const tampilanDaftar = document.querySelector("#form-register");

  if (!tampilanMasuk || !tampilanDaftar) {
    return;
  }

  tombolTab.forEach(function (tombol) {
    tombol.addEventListener("click", function () {
      const daftar = tombol.dataset.tab === "register";
      tampilanMasuk.classList.toggle("hidden", daftar);
      tampilanDaftar.classList.toggle("hidden", !daftar);
    });
  });
}


function aktifkanTombolMata() {
  document.querySelectorAll("[data-mata]").forEach(function (tombol) {
    tombol.addEventListener("click", function () {
      const kolom = tombol.parentElement.querySelector("input");
      const tampil = kolom.type === "password";

      kolom.type = tampil ? "text" : "password";
      tombol.firstElementChild.classList.toggle("hidden", tampil);
      tombol.lastElementChild.classList.toggle("hidden", !tampil);
    });
  });
}


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

function aktifkanValidasiForm() {
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
}


function aktifkanKonfirmasi() {
  document.querySelectorAll("form[data-konfirmasi]").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      if (!confirm(form.dataset.konfirmasi)) {
        e.preventDefault();
      }
    });
  });
}


function aktifkanPencarianTabel() {
  const kolomCari = document.querySelector("#cari");
  if (!kolomCari) {
    return;
  }

  kolomCari.addEventListener("input", function () {
    const kata = kolomCari.value.toLowerCase();

    document.querySelectorAll("#tabel-cari tbody tr").forEach(function (baris) {
      const cocok = (baris.textContent || "").toLowerCase().includes(kata);
      baris.style.display = cocok ? "" : "none";
    });
  });
}


function aktifkanPotongFoto() {
  const inputFoto = document.querySelector("#input-foto");
  const pratinjau = document.querySelector("#pratinjau");
  const modalCrop = document.querySelector("#modal-crop");

  if (!inputFoto || !pratinjau || !modalCrop) {
    return;
  }

  const kanvas = document.querySelector("#kanvas-crop");
  const geserZoom = document.querySelector("#zoom-crop");
  const konteks = kanvas.getContext("2d");
  const UKURAN = kanvas.width;
  const HASIL = 400;

  let gambar = null;
  let zoom = 1;
  let kiri = 0;
  let atas = 0;
  let seret = null;

  function skala() {
    const dasar = Math.max(UKURAN / gambar.naturalWidth, UKURAN / gambar.naturalHeight);
    return dasar * zoom;
  }

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
      kiri = (UKURAN - img.naturalWidth * skala()) / 2;
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

  kanvas.addEventListener("pointerdown", function (e) {
    seret = { x: e.clientX, y: e.clientY, kiri: kiri, atas: atas };
    kanvas.setPointerCapture(e.pointerId);
  });

  kanvas.addEventListener("pointermove", function (e) {
    if (!seret) {
      return;
    }

    const rasio = UKURAN / kanvas.getBoundingClientRect().width;

    kiri = seret.kiri + (e.clientX - seret.x) * rasio;
    atas = seret.atas + (e.clientY - seret.y) * rasio;
    batasi();
    gambarUlang();
  });

  kanvas.addEventListener("pointerup", function () {
    seret = null;
  });

  kanvas.addEventListener("pointercancel", function () {
    seret = null;
  });

  document.querySelector("#crop-pakai").addEventListener("click", function () {
    const hasil = document.createElement("canvas");
    hasil.width = HASIL;
    hasil.height = HASIL;

    const pena = hasil.getContext("2d");
    pena.fillStyle = "#ffffff";
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


function aktifkanSidebar() {
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
    document.body.classList.add("overflow-hidden");
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
      }
    });
  }
}


function aktifkanKolomNomor() {
  document.querySelectorAll("input[data-nomor]").forEach(function (kolom) {
    kolom.addEventListener("keydown", function (e) {
      if (["e", "E", "+", "-", ".", ","].includes(e.key)) {
        e.preventDefault();
      }
    });
  });
}


function aktifkanKolomAngka() {
  document.querySelectorAll("input[data-angka]").forEach(function (kolom) {
    kolom.addEventListener("input", function () {
      const bersih = kolom.value.replace(/\D/g, "");
      if (bersih !== kolom.value) {
        kolom.value = bersih;
      }
    });
  });
}


function aktifkanPratinjauBerkas() {
  const modal = document.querySelector("#modal-preview");
  if (!modal) {
    return;
  }

  const judul = document.querySelector("#modal-judul");
  const isi = document.querySelector("#modal-isi");
  const tautanUnduh = document.querySelector("#modal-unduh");
  const GAMBAR = ["jpg", "jpeg", "png", "webp", "gif"];

  function pesanKecil(teks, kelas) {
    const p = document.createElement("p");
    p.className = kelas;
    p.textContent = teks;
    return p;
  }

  function tutup() {
    modal.classList.add("hidden");
    modal.classList.remove("flex");
    isi.innerHTML = "";
  }

  function buka(url, teksJudul, ekstensi) {
    ekstensi = (ekstensi || "").toLowerCase();
    judul.textContent = teksJudul || "Pratinjau dokumen";
    tautanUnduh.href = url + "&unduh=1";

    isi.innerHTML = "";
    isi.appendChild(pesanKecil("Memuat berkas...", "text-xs text-stone-500"));

    if (GAMBAR.includes(ekstensi)) {
      const img = document.createElement("img");
      img.src = url;
      img.alt = teksJudul;
      img.className = "mx-auto max-h-[70vh] max-w-full rounded object-contain";

      img.onload = function () {
        isi.innerHTML = "";
        isi.appendChild(img);
      };
      img.onerror = function () {
        isi.innerHTML = "";
        isi.appendChild(pesanKecil("Gagal memuat gambar atau format tidak didukung.", "text-xs font-medium text-danger-700"));
      };
    } else if (ekstensi === "pdf") {
      const iframe = document.createElement("iframe");
      iframe.src = url;
      iframe.className = "h-[70vh] w-full rounded border border-stone-200 bg-white";

      isi.innerHTML = "";
      isi.appendChild(iframe);
    } else {
      const kotak = document.createElement("div");
      kotak.className = "py-8 text-center";

      const unduh = document.createElement("a");
      unduh.href = url + "&unduh=1";
      unduh.className = "btn btn-utama btn-kecil";
      unduh.textContent = "Unduh berkas (" + ekstensi.toUpperCase() + ")";

      kotak.appendChild(pesanKecil("Pratinjau langsung tidak tersedia", "mb-1 text-sm font-semibold text-stone-700"));
      kotak.appendChild(pesanKecil("Format berkas ." + ekstensi + " dapat dibuka setelah diunduh.", "mb-4 text-xs text-stone-500"));
      kotak.appendChild(unduh);

      isi.innerHTML = "";
      isi.appendChild(kotak);
    }

    modal.classList.remove("hidden");
    modal.classList.add("flex");
  }

  document.querySelectorAll("[data-lihat]").forEach(function (tombol) {
    tombol.addEventListener("click", function () {
      buka(tombol.dataset.url, tombol.dataset.judul, tombol.dataset.ekstensi);
    });
  });

  document.querySelectorAll("[data-tutup-pratinjau]").forEach(function (tombol) {
    tombol.addEventListener("click", tutup);
  });

  modal.addEventListener("click", function (e) {
    if (e.target === modal) {
      tutup();
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && modal.classList.contains("flex")) {
      tutup();
    }
  });
}


function aktifkanDurasiCuti() {
  const tglMulai = document.querySelector("#tgl-mulai");
  const tglSelesai = document.querySelector("#tgl-selesai");
  const infoDurasi = document.querySelector("#info-durasi");
  const teksDurasi = document.querySelector("#teks-durasi");

  if (!tglMulai || !tglSelesai || !infoDurasi || !teksDurasi) {
    return;
  }

  function hitungHari() {
    if (!tglMulai.value || !tglSelesai.value) {
      return;
    }

    const d1 = new Date(tglMulai.value);
    const d2 = new Date(tglSelesai.value);
    const selisih = Math.floor((d2 - d1) / (1000 * 60 * 60 * 24)) + 1;

    if (selisih > 0) {
      teksDurasi.textContent = selisih;
      infoDurasi.classList.remove("hidden");
    } else {
      infoDurasi.classList.add("hidden");
    }
  }

  tglMulai.addEventListener("change", hitungHari);
  tglSelesai.addEventListener("change", hitungHari);
}


function aktifkanJamDigital() {
  const jam = document.querySelector("#jam-digital");
  if (!jam) {
    return;
  }

  setInterval(function () {
    jam.textContent = new Date().toLocaleTimeString("id-ID", { hour12: false });
  }, 1000);
}


aktifkanTabLogin();
aktifkanTombolMata();
aktifkanValidasiForm();
aktifkanKonfirmasi();
aktifkanPencarianTabel();
aktifkanPotongFoto();
aktifkanSidebar();
aktifkanKolomNomor();
aktifkanKolomAngka();
aktifkanPratinjauBerkas();
aktifkanDurasiCuti();
aktifkanJamDigital();

const video = document.getElementById("video");
const facePreview = document.getElementById("facePreview");
const submitBtn = document.getElementById("submitBtn");
const capturedFace = document.getElementById("captured_face");
const form = document.getElementById("docForm");
const username = document.getElementById("username").value;

// ----------------------------
// MASKED PASSWORD INPUT MODAL WITH TOGGLE PADLOCK
// ----------------------------
function askPassword() {
  return new Promise((resolve) => {
    const modal = document.getElementById("passwordModal");
    const passInput = document.getElementById("modalPassword");
    const submitBtn = document.getElementById("modalSubmit");
    const togglePassword = document.getElementById("togglePassword");

    let realPassword = ""; // store actual password
    let isHidden = true; // password hidden by default

    modal.style.display = "flex";
    passInput.value = "";
    passInput.focus();

    // Mask input with padlock emoji
    passInput.addEventListener("input", (e) => {
      const typed = e.data;

      if (!typed) {
        // handle backspace
        realPassword = realPassword.slice(0, -1);
      } else {
        realPassword += typed;
      }

      // replace display with padlock icons only if hidden
      if (isHidden) {
        passInput.value = "🔒".repeat(realPassword.length);
      }
    });

    // Toggle padlock visibility
    togglePassword.onclick = () => {
      isHidden = !isHidden;
      passInput.type = isHidden ? "password" : "text";
      togglePassword.textContent = isHidden ? "🔒" : "🔓";

      if (isHidden) {
        passInput.value = "🔒".repeat(realPassword.length);
      } else {
        passInput.value = realPassword;
      }
      passInput.focus();
    };

    submitBtn.onclick = () => {
      modal.style.display = "none";
      resolve(realPassword);
    };
  });
}

// ----------------------------
// FACE VALIDATION + PASSWORD CONFIRMATION
// ----------------------------
form.addEventListener("submit", async (e) => {
  e.preventDefault();

  if (!capturedFace.value) {
    return alert("Please capture your face first.");
  }

  const formData = new FormData(form);
  formData.append("captured_face", capturedFace.value);

  try {
    const verifyResp = await fetch("http://127.0.0.1:8000/api/verify_face/", {
      method: "POST",
      body: formData,
    });
    const verifyJson = await verifyResp.json();

    if (verifyJson.match) {
      alert(`Face matched with: ${verifyJson.match}`);

      const password = await askPassword();
      if (!password) return alert("Password is required to continue.");

      const passData = new FormData();
      passData.append("username", username);
      passData.append("password", password);

      const passResp = await fetch("http://127.0.0.1:8000/api/login/", {
        method: "POST",
        body: passData,
      });
      const passJson = await passResp.json();

      if (passJson.error) return alert("Incorrect password. Please try again.");

      const docResp = await fetch("process_document_request.php", {
        method: "POST",
        body: formData,
      });

      const result = await docResp.json();
      if (result.redirect) window.location.href = result.redirect;
    } else {
      alert("Face verification failed. Please try again.");
    }
  } catch (err) {
    alert("Error during verification: " + err.message);
  }
});

// ----------------------------
// CAMERA / FACE CAPTURE
// ----------------------------
let streamRef = null;
let faceCaptured = false;

function startCamera() {
  navigator.mediaDevices
    .getUserMedia({ video: { facingMode: "user" } })
    .then((stream) => {
      video.srcObject = stream;
      streamRef = stream;
      video.style.display = "block";
    })
    .catch(() => alert("Unable to access camera."));
}

function stopCamera() {
  if (streamRef) {
    streamRef.getTracks().forEach((track) => track.stop());
    streamRef = null;
  }
  video.style.display = "none";
}

function captureFace() {
  if (faceCaptured) {
    const recapture = confirm(
      "You already captured your face. Do you want to capture again?"
    );
    if (!recapture) return;

    facePreview.style.display = "none";
    capturedFace.value = null;
    submitBtn.disabled = true;
    faceCaptured = false;
    startCamera();
    return;
  }

  const canvas = document.createElement("canvas");
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext("2d");

  ctx.save();
  ctx.scale(-1, 1);
  ctx.drawImage(video, -canvas.width, 0, canvas.width, canvas.height);
  ctx.restore();

  const imageData = canvas.toDataURL("image/png");
  facePreview.src = imageData;
  facePreview.style.display = "block";

  faceCaptured = true;
  stopCamera();
  submitBtn.disabled = false;

  const file = dataURLtoFile(imageData, "profile.jpg");
  const dataTransfer = new DataTransfer();
  dataTransfer.items.add(file);
  captured_face.files = dataTransfer.files;
}

function dataURLtoFile(dataurl, filename) {
  let arr = dataurl.split(","),
      mime = arr[0].match(/:(.*?);/)[1],
      bstr = atob(arr[1]),
      n = bstr.length,
      u8arr = new Uint8Array(n);

  while (n--) u8arr[n] = bstr.charCodeAt(n);

  return new File([u8arr], filename, { type: mime });
}

startCamera();

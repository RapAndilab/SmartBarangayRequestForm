// Document-request submission (no face verification).
// Submits the form to process_document_request.php, then redirects to the
// "request submitted / pending approval" page.
const form = document.getElementById("docForm");

form.addEventListener("submit", async (e) => {
  e.preventDefault();

  const submitBtn = document.getElementById("submitBtn");
  if (submitBtn) submitBtn.disabled = true;

  try {
    const formData = new FormData(form);
    const resp = await fetch("process_document_request.php", {
      method: "POST",
      body: formData,
    });
    const result = await resp.json();

    if (result.error) {
      alert("Error: " + result.error);
      if (submitBtn) submitBtn.disabled = false;
      return;
    }
    if (result.redirect) {
      window.location.href = result.redirect;
    }
  } catch (err) {
    alert("Error submitting request: " + err.message);
    if (submitBtn) submitBtn.disabled = false;
  }
});

import { realisticConfetti } from "./confetti.js";

const compressionStrengthText = document.getElementById("compressionStrengthText");
const compressionStrengthSlider = document.getElementById("compressionStrength");
const result = document.getElementById("result");
const submit = document.querySelector("button[type='submit']");

document.getElementById('urlForm').addEventListener('submit', function(event) {
    event.preventDefault();
    submit.disabled=true;
    result.innerHTML='<progress />';
    const url = document.getElementById('websiteUrl').value;
    const compression = compressionStrengthSlider.value;
    
    fetch('process.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'url=' + encodeURIComponent(url)+'&compression='+compression
    })
    .then(response => response.json())
    .then(data => {
        result.innerHTML = data.message;
        if (data.download) {
            realisticConfetti();
            result.innerHTML += `<br><a href="${data.download}" download><button><i class="fa-solid fa-download"></i> Stáhnout optimalizované obrázky</button></a>`;
            result.innerHTML += `<br><span>Původní velikost: <strong>${data.originalSize}</strong></span>`;
            result.innerHTML += `<br><span>Finální velikost: <strong>${data.optimizedSize}</strong></span>`;
            result.innerHTML += `<br><span>Ušetřeno: <strong>${data.savedSize} (${data.compressionPercentage})</strong></span>`;
        }
    })
    .catch(error => console.error('Chyba:', error)).finally(() => {
        submit.disabled=false;
    });
});


compressionStrengthSlider.addEventListener("input", () => {
    compressionStrengthText.textContent = compressionStrengthSlider.value+"%"; 
})

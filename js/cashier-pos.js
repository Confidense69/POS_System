const input = document.getElementById('barcodeInput');
const btn =  document.getElementById('addItemBtn');
const tbody = document.getElementById('salesTableBody');

async function addByBarCode() {

    const barcode = input.value.trim();
    if (barcode === '') return;



    const res  = await fetch('http://localhost/pos/api/products.php?barcode=' + encodeURIComponent(barcode));
    const data = await res.json();

    if (data.length === 0){
        alert('No product with that barcode');
    }else{
        renderProduct(data[0]);
    }
    input.value = ''; 
    
}

    function renderProduct(p){
        const empty = document.getElementById('emptyRow');
        if (empty) empty.remove();

        const tr = document.createElement('tr');
        const td1 = document.createElement('td');   td1.textContent = p.name
        const tdqty = document.createElement('td'); tdqty.textContent = 1; tdqty.classList.add("text-center");
        const tdprc = document.createElement('td'); tdprc.textContent = parseFloat(p.price).toFixed(2); tdprc.classList.add("text-end");
        const tdtot = document.createElement('td'); tdtot.textContent = parseFloat(p.price).toFixed(2); tdtot.classList.add("text-end");
        tr.append(td1, tdqty, tdprc, tdtot);
        tbody.appendChild(tr);
    }
    
    btn.addEventListener('click', addByBarCode);
    input.addEventListener('keydown', function (e){
        if (e.key === 'Enter') addByBarCode();
    });
   
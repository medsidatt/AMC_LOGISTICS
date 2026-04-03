// import axios from 'axios';
window.applyRecordToForm = function applyRecordToForm() {
    const form = document.getElementById('add-adjustment-form');
    const nameSelect = form.querySelector('select[name="name"]');
    const selectedName = nameSelect.value;

    if (!selectedName) return;

    /*fetch(`/payrolls/addition/${encodeURIComponent(selectedName)}`)
        .then(response => response.json())
        .then(data => {
            if (!data) {
                // No existing addition, clear fields or leave as is
                return;
            }

            // Fill form fields from DB response
            form.querySelector('select[name="type"]').value = data.type;
            form.querySelector('select[name="nature"]').value = data.nature;
            form.querySelector('input[name="amount"]').value = data.amount;
            form.querySelector('input[name="quantity"]').value = data.quantity;

            form.querySelector('#cnss').checked = !!data.cnss;
            form.querySelector('#cnam').checked = !!data.cnam;
            form.querySelector('#its').checked = !!data.its;

            // trigger change events if using select2
            $(form.querySelector('select[name="type"]')).trigger('change');
            $(form.querySelector('select[name="nature"]')).trigger('change');
        })
        .catch(error => console.error('Error fetching addition:', error));*/

    /*axios.get(`/payrolls/addition/${encodeURIComponent(selectedName)}`)
        .then(response => {
            const data = response.data;
            if (!data) {
                // No existing addition, clear fields or leave as is
                return;
            }

            // Fill form fields from DB response
            form.querySelector('select[name="type"]').value = data.type;
            form.querySelector('select[name="nature"]').value = data.nature;
            form.querySelector('input[name="amount"]').value = data.amount;
            form.querySelector('input[name="quantity"]').value = data.quantity;

            form.querySelector('#cnss').checked = !!data.cnss;
            form.querySelector('#cnam').checked = !!data.cnam;
            form.querySelector('#its').checked = !!data.its;

            // trigger change events if using select2
            $(form.querySelector('select[name="type"]')).trigger('change');
            $(form.querySelector('select[name="nature"]')).trigger('change');
        })
        .catch(error => console.error('Error fetching addition:', error));
*/

    $.ajax({
        url: `/payrolls/addition/${encodeURIComponent(selectedName)}`,
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            if (!data) {
                // No existing addition, clear fields or leave as is
                return;
            }

            // Fill form fields from DB response
            form.querySelector('select[name="type"]').value = data.type;
            form.querySelector('select[name="nature"]').value = data.nature;
            form.querySelector('input[name="amount"]').value = data.amount;
            form.querySelector('input[name="quantity"]').value = data.quantity;

            form.querySelector('#cnss').checked = !!data.cnss;
            form.querySelector('#cnam').checked = !!data.cnam;
            form.querySelector('#its').checked = !!data.its;

            // trigger change events if using select2
            $(form.querySelector('select[name="type"]')).trigger('change');
            $(form.querySelector('select[name="nature"]')).trigger('change');
        },
        error: function(xhr, status, error) {
            console.error('Error fetching addition:', error);
        }
    });
}



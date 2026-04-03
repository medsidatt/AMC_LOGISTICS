const calculateSalaire = () => {
    // Gather form data
    const montant = document.querySelector('input[name="montant"]').value;
    const hasCNSS = document.querySelector('input[name="has_cnss"]').checked ? 1 : 0;
    const hasCNAM = document.querySelector('input[name="has_cnam"]').checked ? 1 : 0;
    const typeMontant = document.querySelector('input[name="type_montant"]:checked').value;

    // route from form action
    const route = document.querySelector('#calculete_salaire_form').getAttribute('action');
    // console.log(route);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    if (montant === '') {
        document.querySelector('input[name="montant"]').classList.add('is-invalid');
        if (!document.querySelector('.invalid-feedback')) {
            document.querySelector('input[name="montant"]').insertAdjacentHTML('afterend', '<div class="invalid-feedback">Ce champ est obligatoire</div>');
        }
        return;
    }

    document.querySelector('input[name="montant"]').classList.remove('is-invalid');
    if (document.querySelector('.invalid-feedback')) {
        document.querySelector('.invalid-feedback').remove();
    }

    $.ajax({
        url: route,
        type: 'POST',
        data: {
            _token: csrfToken,
            montant: montant,
            has_cnss: hasCNSS,
            has_cnam: hasCNAM,
            type_montant: typeMontant
        },
        success: function (data) {
            document.querySelector('.result').innerHTML = data;
        },
        error: function (error) {
            console.error(error);
        }
    });
}

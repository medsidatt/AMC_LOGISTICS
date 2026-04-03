(function (window, undefined) {
    'use strict';


})(window);

$(document).ready(function () {
    fetchTranslations();

    initJs()

    $('table[data-column]').each(function () {
        let table = $(this);
        let columns = table.data('column').split(',');
        let url = table.data('url');
        let params = table.data('params');
        let priorities = table.data('priorities');
        let priorityMap = {};
        if (priorities) {
            String(priorities).split(',').forEach(function (val, idx) {
                priorityMap[idx] = parseInt(val);
            });
        }

        // Build columnDefs: first column and actions (last) always visible
        let columnDefs = [
            { responsivePriority: 1, targets: 0 },
            { responsivePriority: 2, targets: -1 }
        ];
        // Apply custom priorities from data-priorities attribute
        Object.keys(priorityMap).forEach(function (idx) {
            columnDefs.push({ responsivePriority: priorityMap[idx], targets: parseInt(idx) });
        });

        // Use simple prev/next on mobile, full numbers on desktop
        let isMobile = window.innerWidth < 768;
        const dataTable = table.DataTable({
            processing: true,
            serverSide: true,
            search: true,
            pagingType: isMobile ? 'simple' : 'simple_numbers',
            responsive: {
                details: {
                    type: 'column',
                    target: 'tr'
                }
            },
            autoWidth: false,
            columnDefs: columnDefs,
            ajax: {
                url: url, type: 'GET', data: function (d) {
                    d.customRequest = {}
                    d.filters = {};
                    d.params = params;
                    $('[data-filter]').each(function () {
                        let filterInput = $(this);
                        let filterType = filterInput.data('filter');
                        d.filters[filterType] = filterInput.val();
                    });

                    // add custom request data
                    $('[data-request]').each(function () {
                        let requestInput = $(this);
                        let requestType = requestInput.data('request');
                        d.customRequest[requestType] = requestInput.val();
                    });

                    return d;
                } // Pass filters as data to your server
            },
            columns: columns.map(function (col) {
                return {
                    data: col, name: col
                }
            }),
            order: table.data('default-order')
                ? [[parseInt(table.data('default-order').split(',')[0]), table.data('default-order').split(',')[1]]]
                : [[0, 'asc']]
        });

        $('[data-filter]').on('change', function () {
            dataTable.draw();
        });
    });


    document.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            const activeElement = document.activeElement;
            if (activeElement.tagName === 'INPUT' || activeElement.tagName === 'SELECT') {
                const form = activeElement.closest('form');
                if (form) {
                    const submitButton = form.querySelector('button[onclick]');
                    if (submitButton) {
                        submitButton.click();
                        event.preventDefault();
                    }
                }
            }

            if (activeElement.tagName !== 'TEXTAREA' && activeElement.tagName !== 'BUTTON') {
                event.preventDefault();
                return false;
            }
        }
    });

});

function fetchTranslations() {
    fetch('/translations')
        .then(response => response.json())
        .then(translations => {
            window.translations = translations;
        });
}

// Call this function on language switch or on page load


function logOut() {
    Swal.fire({
        title: 'Confirmer!',
        text: 'Voulez-vous vraiment vous déconnecter?',
        type: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, déconnecter!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.value) {
            $('#logout-form').submit();
        } else {
            return false;
        }
    })
}

function showSpinner() {
    document.getElementById('spinnerOverlay').classList.add('show');
}

function hideSpinner() {
    document.getElementById('spinnerOverlay').classList.remove('show');
}


// loading spinner
function showLoadingSpinner(element) {
    let timerInterval;
    Swal.fire({
        title: "Auto close alert!",
        html: "I will close in <b></b> milliseconds.",
        timer: 2000, // timerProgressBar: true,
        didOpen: () => {
            Swal.showLoading();
            const timer = Swal.getPopup().querySelector("b");
            timerInterval = setInterval(() => {
                timer.textContent = `${Swal.getTimerLeft()}`;
            }, 100);
        },
        willClose: () => {
            clearInterval(timerInterval);
        }
    }).then((result) => {
        /* Read more about handling dismissals below */
        if (result.dismiss === Swal.DismissReason.timer) {
            console.log("I was closed by the timer");
        }
    });
}


const Toast = Swal.mixin({
    toast: true, position: "top-end", showConfirmButton: false, timer: 3000,
});

function initJs() {
    // select2
    $('.select2').select2({
        width: '100%',
    });

    $('.select2-no-search').select2({
        theme: 'bootstrap4', allowClear: true, width: '100%', minimumResultsForSearch: Infinity
    });

    // muliple select2 with tags
    $('.select2-tags').select2({
        width: '100%', tags: true,
    });

}


function confirmAction({route, data, message}) {
    Swal.fire({
        title: 'Confirmer!',
        text: message,
        type: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.value) {
            $.ajax({
                url: route,
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: data,
                success: function (response) {
                    Toast.fire({
                        type: 'success',
                        title: response.message,
                    });
                    if (response.redirectRoute) {
                        setTimeout(function () {
                            window.location.href = response.redirectRoute;
                        }, 2000);
                    }
                    $('table[data-column]').each(function () {
                        let table = $(this);
                        let dataTable = table.DataTable();
                        dataTable.ajax.reload();
                    });
                }, error: function (error) {
                    Toast.fire({
                        type: 'error', title: 'Error!', text: error.responseJSON.error
                    });
                },

            })
        }
    });
}


//removeElement(this)
function removeElement(element) {
    const closestLi = element.closest('li');
    const closestTr = element.closest('tr');

    if (closestLi) {
        closestLi.remove();
    } else if (closestTr) {
        closestTr.remove();
    } else {
        console.warn('No <li> or <tr> found to remove.');
    }
}

// check if the user needs to change the password
function checkPasswordChange() {
    $.ajax({
        url: window.location.href,  // Send request to current page URL
        method: 'GET',  // Assuming it's a GET request
        success: function (response) {
            console.log("Page loaded successfully.");
        }, error: function (xhr) {
            console.log(xhr.status)
            if (xhr.status === 403) {  // Check if the user needs to change the password
                const data = xhr.responseJSON;
                alert(data.message);  // Show alert or other notification
                window.location.href = data.redirect_url;  // Redirect to the password change page
            }
        }
    });
}

// target
window.loadDropdown = function ({url, params, target, defaultOption = '', formatOption}) {
    // alert('here')
    console.log(url, params, target, defaultOption, formatOption);
    let dropdown = $('#' + target);
    url = url + '?' + $.param(params);
    $.ajax({
        url: url,
        type: 'GET',
        data: params,
        success: function (data) {
            console.log(data);
            dropdown.empty();
            dropdown.append('<option value="">' + defaultOption + '</option>');
            $.each(data, function (index, item) {
                dropdown.append(`<option value="${item.id}">${formatOption(item)}</option>`);
            });
        }, error: function (error) {
            console.log(error);
        }
    });
}

function handleDependentDropdown(parentDropdown, childDropdown, urlTemplate, defaultOption, formatOption) {
    const parentId = parentDropdown.val();
    console.log(parentId)
    if (parentId) {
        loadDropdown(urlTemplate.replace(':id', parentId), childDropdown, defaultOption, formatOption);
    } else {
        childDropdown.empty();
        childDropdown.append('<option value="">' + defaultOption + '</option>');
    }
}

function deleteRecord(url, modal, afterDelete = null) {
    Swal.showLoading();
    $.ajax({
        url: url, method: 'DELETE', headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }, success: function (response) {
            Swal.fire({
                type: 'success',
                title: response.message,
            })
            $('table[data-column]').each(function () {
                let table = $(this);
                let dataTable = table.DataTable();
                dataTable.ajax.reload();
            });

            if (response.redirectRoute) {
                setTimeout(function () {
                    window.location.href = response.redirectRoute;
                }, 2000);
            }

            if (afterDelete && typeof afterDelete === 'function') {
                afterDelete(response);
                return;
            }

            if (response.refreshRoute && modal) {
                const modalBody = $('#' + modal + '-modal .modal-body');
                $.ajax({
                    url: response.refreshRoute,
                    type: 'GET',
                    success: function (response) {
                        modalBody.html(response);
                        initJs();
                    },
                    error: function () {
                        modalBody.html(`
                            <div class="alert alert-danger">
                                <strong>Error!</strong> Failed to reload modal content.
                            </div>
                        `);
                    }
                });
            }

        }, error: function (error) {
            Swal.fire({
                type: 'error',
                title: "Error!",
                text: error.responseJSON.message
            })
        }
    });

}

function confirmDelete(url, modal = 'dynamic', afterDelete = null) {
    Swal.fire({
        title: 'Confirmer!',
        text: 'Voulez-vous vraiment supprimer cet enregistrement?',
        type: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.value) {
            deleteRecord(url, modal, afterDelete);
        } else {
            return false;
        }
    })
}

// show indicator progress
function showIndicatorProgress(element) {
    $(element).attr('disabled', 'disabled');
    $(element).find('.main-icon').hide();
    $(element).find('.indicator-progress').removeClass('d-none');
}

// hide indicator progress
function hideIndicatorProgress(element) {
    $(element).removeAttr('disabled');
    $(element).find('.main-icon').show();
    $(element).find('.indicator-progress').addClass('d-none');
}

function openInModal({link, element, callback = null, modal = "main", size = "lg", is_static = true}) {
    showIndicatorProgress(element);
    // showSpinner()
    $.ajax({
        type: 'get', url: link, headers: {
            'X-Requested-With': 'XMLHttpRequest', 'X-App-Request': 'MySecretToken123' // Your custom token
        }, success: function (data) {

            hideIndicatorProgress(element);

            const modalId = `${modal}-modal`;
            const modalElement = document.getElementById(modalId);

            if (modalElement) {
                const modalDialog = modalElement.querySelector('.modal-dialog');
                modalDialog.className = `modal-dialog modal-${size}`;
                modalElement.querySelector('.modal-header-body').innerHTML = data;
                const activeModal = new bootstrap.Modal(modalElement, {
                    backdrop: is_static ? 'static' : true, // Manage backdrop
                    keyboard: !is_static, // Disable keyboard dismiss if static
                });
                activeModal.show();
                initJs();
                // hideSpinner();
                if (callback) callback();
            }

            if (callback) callback();
        }, error: function (xhr) {
            // hideSpinner();
            hideIndicatorProgress(element);
            Toast.fire({
                type: 'error', title: 'Error!', text: 'Une erreur s\'est produite lors du chargement de la page'
            });
            const data = xhr.responseJSON;
            if (xhr.status === 403 && xhr.responseJSON.status === 'error_change_password') {
                Swal.fire({
                    title: 'Changer le mot de passe',
                    text: data.message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, changer le mot de passe',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        openInModal({
                            link: data.redirect_url, element: element, modal: 'main', size: 'md',
                        });
                    }
                });
            }
        }
    });
}

function saveForm({element, afterSave = null, autoClose = true, modal = 'main', refreshAfterSave = false}) {
    const container = $(element).attr('container');
    const activeModal = $('#' + modal + '-modal');
    const form = $('#' + container + ' form');
    $(element).attr('disabled', 'disabled');
    const mainIcon = $('#' + container + ' .main-icon');
    const wellSaved = $('#' + container + ' .well-saved');
    const indicatorProgress = $('#' + container + ' .indicator-progress');
    mainIcon.hide();
    indicatorProgress.removeClass('d-none');
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        type: form.attr("method"),
        url: form.attr("action"),
        data: new FormData(form[0]),
        cache: false,
        contentType: false,
        processData: false,
        success: function (data) {
            const redirectRoute = data.redirectRoute;
            const fileUrl = data.fileUrl;
            const refreshRoute = data.refreshRoute;
            $(element).removeAttr('disabled');
            wellSaved.removeClass('d-none');
            mainIcon.hide();
            indicatorProgress.addClass('d-none');
            setTimeout(function () {
                wellSaved.addClass('d-none');
                mainIcon.show();
            }, 3000);

            if (fileUrl && fileUrl !== '') {
                Swal.fire({
                    type: 'success', title: 'Fichier créé avec succès!',
                    // text: `Le fichier est disponible pour téléchargement.`,
                    text: data.message || 'Le fichier est disponible pour téléchargement.',
                    showConfirmButton: true,
                    confirmButtonText: 'Telécharger',
                    didOpen: () => {
                        const confirmButton = Swal.getConfirmButton();
                        confirmButton.classList.add('btn', 'btn-primary');
                    },
                    // show CancelButton: true,
                }).then(() => {
                    activeModal.hide();
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open');
                    $('body').css('padding-right', '');
                    const link = document.createElement('a');
                    link.href = fileUrl; // your file URL here
                    link.download = ''; // optionally specify a filename
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                })
                return
            }

            if (redirectRoute === undefined) {
                Toast.fire({
                    type: 'success', title: data.message, // timerProgressBar: true,
                    timer: 3000,
                }).then(response => {
                    if (autoClose) {
                        activeModal.hide()
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open');
                        $('body').css('padding-right', '');
                    }
                }).finally(() => {
                    if (afterSave && typeof afterSave === 'function') {
                        afterSave(data);
                    }
                });
            }
            $('#' + container + ' .is-invalid').each(function (index, item) {
                $(item).removeClass('is-invalid');
            });
            $('#' + container + ' .invalid-feedback').each(function (index, item) {
                $(item).remove();
            });
            $('#' + container + ' .select2-invalid-feedback').each(function (index, item) {
                $(item).remove();
            });
            $('table[data-column]').each(function () {
                let table = $(this);
                let dataTable = table.DataTable();
                dataTable.ajax.reload();
            });
            if (redirectRoute) {
                Swal.fire({
                    type: 'success', title: data.message, showConfirmButton: true, confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = redirectRoute;
                })
            }
            if (refreshAfterSave && refreshRoute) {
                const modalBody = $('#' + modal + '-modal .modal-body');
                $.ajax({
                    url: refreshRoute,
                    type: 'GET',
                    success: function (response) {
                        modalBody.html(response);
                        initJs();
                        if (afterSave && typeof afterSave === 'function') {
                            afterSave(data);
                        }
                    },
                    error: function () {
                        modalBody.html(`
                            <div class="alert alert-danger">
                                <strong>Error!</strong> Failed to reload modal content.
                            </div>
                        `);
                    }
                });
            }
        },
        error: function (data) {
            // hideSpinner();
            mainIcon.show();
            indicatorProgress.addClass('d-none');
            $(element).removeAttr('disabled');
            if (data.status === 422) {
                const errors = data.responseJSON;
                const erreurs = (errors.errors) ? errors.errors : errors;
                $.each($('#' + container + ' form input'), function (key, item) {
                    let input = $(item);
                    if (!(input.attr('name') in erreurs)) {
                        input.next('.invalid-feedback').remove();
                        input.removeClass('is-invalid');
                    }
                });

                $.each(erreurs, function (key, value) {
                    const formControl = $('#' + container + ' .form-control[name=' + key + ']');
                    const input = formControl;
                    if (input.attr('name') === key) {
                        if (input.hasClass('select2')) {
                            const span = formControl;
                            if (input.parent().parent().parent().hasClass('input-group')) {
                                span.parent().parent().parent('.select2-invalid-feedback').remove();
                                $(`<strong class='select2-invalid-feedback'>${value}</strong>`).insertAfter(input.parent().parent().parent());
                            } else {
                                span.next().next('.select2-invalid-feedback').remove();
                                $(`<strong class='select2-invalid-feedback'>${value}</strong>`).insertAfter(span.next());
                            }
                        } else {
                            input.next('.invalid-feedback').remove();
                            $(`<strong class='invalid-feedback'>${value}</strong>`).insertAfter(input);
                        }
                        input.addClass('is-invalid');
                    }
                });

                $('#' + container + ' .form-control').on('change', function () {
                    $(this).next('.invalid-feedback').remove();
                    $(this).removeClass('is-invalid');
                });

                const select2 = $('#' + container + ' .select2');

                select2.change(function () {
                    $(this).next().next('.select2-invalid-feedback').remove();
                    $(this).removeClass('is-invalid');
                });

                select2.change(function () {
                    $(this).parent().parent().parent().next('.select2-invalid-feedback').remove();
                    $(this).removeClass('select2-is-invalid');
                });

                if (errors.errors) {
                    let form = $('#' + container + ' form');
                    $('#' + container).find('.alert-danger').remove();
                }

            } else if (data.status === 400) {
                Swal.fire({
                    icon: 'error', title: 'Error', text: data.responseJSON.error
                })
            } else {
                Toast.fire({
                    type: 'error', title: data.responseJSON.message || 'Une erreur s\'est produite lors de l\'enregistrement!',
                });
            }

            $('#' + container + ' .main-icon').show();
            $(element).removeAttr('disabled');
        },
    });
}

function loadDropdownElements({url, dropdown, defaultOption = '', formatOption}) {
    dropdown = $(dropdown);
    $.ajax({
        url: url, type: 'GET', success: function (data) {
            dropdown.empty();
            // dropdown.append('<option></option>');
            if (defaultOption !== '') {
                dropdown.append('<option>' + defaultOption + '</option>');
            }
            $.each(data, function (index, item) {
                dropdown.append(`<option value="${item.id}">${formatOption(item)}</option>`);
            });
        }, error: function (error) {
            console.log(error);
        }
    });
}


// inject html into a div
window.injectHtml = injectHtml;

function injectHtml({url, container, callback = null}) {
    $.ajax({
        type: 'get', url: url, success: function (data) {
            $(container).append(data);
            initJs()
            if (callback) {
                callback();
            }
        }, error: function (xhr) {
            const data = xhr.responseJSON;
            if (xhr.status === 403 && xhr.responseJSON.status === 'error_change_password') {  // Check if the user needs to change the password
                Swal.fire({
                    title: 'Changer le mot de passe',
                    text: data.message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, changer le mot de passe',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        openInModal({
                            link: data.redirect_url, element: container, modal: 'main', size: 'md',
                        });
                    }
                });
            }
        }
    });
}

function appendToContainer({url, container, callback = null}) {
    // get the container
    container = $(container);
    // make an ajax request
    let existingProducts = [];
    document.querySelectorAll('select[name="products[]"]').forEach(select => {
        existingProducts.push(select.value);
    });

    // Make an AJAX request to get the new row
    $.ajax({
        url: url, type: 'GET', data: {existing_products: existingProducts}, // Send the current product IDs
        success: function (response) {
            // Append the response HTML to the container
            $(container).append(response);
        }, error: function (xhr) {
            console.error('Error fetching new row:', xhr.responseText);
        }
    });
}

function deleteTableRow({element, url}) {
    let button = $(element);
    Swal.fire({
        title: 'Confirmer!',
        text: 'Voulez-vous vraiment supprimer cet enregistrement?',
        type: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.value) {
            $.ajax({
                url: url, method: 'DELETE', headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }, success: function (response) {
                    button.closest('tr').remove();
                    Toast.fire({
                        type: 'success', title: response.message,
                    });
                }, error: function (error) {
                    Toast.fire({
                        type: 'error', title: 'Error!', text: error.responseJSON.error
                    });
                }
            });
        }
    })
}

function validatePlaceholder() {
    const placeholder = $('#placeholder');

    if (!placeholder || placeholder.length === 0) {
        toastr.error('Placeholder element not found');
        return false;
    }

    return true;
}

window.appendRowToTable = function ({url, container}) {
    // alert(url);
    container = $("#" + container);
    $.ajax({
        url: url,
        type: 'GET',
        success: function (response) {
            $(container).append(response);
            initJs();
        },
        error: function (xhr) {
            console.error('Error fetching new row:', xhr.responseText);
        }
    });

}

window.showModal = function ({
                                 route,
                                 title = "[Title...]",
                                 size = "lg",
                                 backdrop = "static",
                                 keyboard = true,
                                 focus = true,
                                 callback = null,
                                 modalId = "dynamic-modal",
                                 file = false
                             }) {

    // Remove existing modal if exists
    let existingModal = document.getElementById(modalId);
    if (existingModal) {
        existingModal.remove();
    }

    // Modal base structure
    const modalHtml = `
        <div class="modal fade" id="${modalId}" tabindex="-1"
            aria-labelledby="${modalId}-label" aria-hidden="true">
            <div class="modal-dialog modal-${size}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="${modalId}-label">${title}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex justify-content-center align-items-center py-4">
                            <div class="spinner-border text-primary"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML("beforeend", modalHtml);

    const modalElement = document.getElementById(modalId);
    const myModal = new bootstrap.Modal(modalElement, { backdrop: backdrop });

    myModal.show();

    // If this is a file preview (PDF or image)
    if (file === true) {

        let ext = route.split(".").pop().toLowerCase();

        // PDF preview
        if (ext === "pdf") {
            modalElement.querySelector(".modal-body").innerHTML = `
                <iframe src="${route}" style="width:100%; height:80vh; border:0;"></iframe>
            `;
        }

        // Image preview
        else if (["jpg","jpeg","png","gif","webp","svg"].includes(ext)) {
            modalElement.querySelector(".modal-body").innerHTML = `
                <img src="${route}" class="img-fluid rounded shadow"
                     style="max-height:80vh; width:100%; object-fit:contain;">
            `;
        }

        // Unsupported file
        else {
            modalElement.querySelector(".modal-body").innerHTML = `
                <p class="text-center text-muted py-3">
                    Prévisualisation non disponible.
                    <br><a href="${route}" download>Télécharger le fichier</a>
                </p>
            `;
        }

        return; // stop here (no AJAX)
    }

    // Normal AJAX loading (HTML blade)
    $.ajax({
        url: route,
        type: "GET",
        success: function (response) {
            modalElement.querySelector(".modal-body").innerHTML = response;
            if (typeof initJs === "function") initJs();
            if (callback) callback(response);
        },
        error: function () {
            modalElement.querySelector(".modal-body").innerHTML = `
                <div class="alert alert-danger">
                    <strong>Error!</strong> An error occurred while loading the content.
                </div>
            `;
        }
    });
};


// enableForm
window.enableForm = function (element) {
    let form = $(element)
    form.find('input, select, textarea, button').each(function () {
        let input = $(this);
        if (input.attr('readonly') || input.attr('disabled')) {
            input.removeAttr('readonly')
            input.removeAttr('disabled')
        } else {
            input.attr('readonly', 'readonly');
            input.attr('disabled', 'disabled');
        }
    });
}

// removeTableRow
window.removeTableRow = function (element) {
    let button = $(element);
    Swal.fire({
        title: 'Confirmer!',
        text: 'Voulez-vous vraiment supprimer cet enregistrement?',
        type: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.value) {
            button.closest('tr').remove();
        }
    })
}

// addTableRow
window.addTableRow = function (element, route) {
    let button = $(element);
    let table = button.closest('table');
    let tbody = table.find('tbody');

    $.ajax({
        url: route,
        type: 'GET',
        success: function (response) {
            tbody.prepend(response);
            initJs();
        },
        error: function (xhr) {
            console.error('Error fetching new row:', xhr.responseText);
        }
    })
}


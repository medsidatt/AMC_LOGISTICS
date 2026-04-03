<x-layouts.main
    title="{{ __('Ask AI') }}"
>
    <div class="card">

        <div class="card-body">
            <form id="ask-ai-form" method="POST" action="{{ route('transport_tracking.analyze-all') }}">
                @csrf
                <div class="mb-3">
                    <label for="question" class="form-label">{{ __('Your Question') }}</label>
                    <textarea id="question" name="question" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('Submit') }}</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h4 class="card-title">{{ __('AI Response') }}</h4>
        </div>
        <div class="card-body">
            <div id="analysis-result">{{--{!! $response['html'] !!}--}}</div>

            <table id="resultTable" class="table table-responsive">

            </table>
            <div id="loading-spinner" class="d-none">
                <div class="d-flex justify-content-center align-items-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden"></span>
                    </div>
                </div>
                <span class="ms-2">{{ __('I\'m thinking. Please wait...') }}</span>
            </div>
        </div>
    </div>

    <script>

        document.getElementById('ask-ai-form').addEventListener('submit', async function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const question = formData.get('question');

            let resultTable = document.getElementById('resultTable');

            const resultDiv = document.getElementById('analysis-result');
            const loadingSpinner = document.getElementById('loading-spinner');

            try {
                loadingSpinner.classList.remove('d-none'); // show spinner

                const response = await fetch("{{ route('transport_tracking.analyze-all') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ question })
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const data = await response.json();

                if (data.success === false) {
                    resultDiv.innerHTML = `<div class="text-danger">Error: ${data.raw}</div>`;
                    console.error('Error:', data.raw);
                } else {
                    // Just render the HTML returned from controller
                    resultDiv.innerHTML = data.html;
                }
            } catch (error) {
                resultDiv.innerHTML = `<div class="text-danger">Unexpected error: ${error.message}</div>`;
                console.error(error);
            } finally {
                loadingSpinner.classList.add('d-none'); // hide spinner
            }


            /*// Clear previous results
            resultTable.innerHTML = '';

            // Create and show loading spinner
            let loadingSpinner = document.getElementById('loadingSpinner');
            loadingSpinner.classList.remove('d-none');


            // return

            try {
                const response = await fetch('{{ route('transport_tracking.analyze-all') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({question})
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const data = await response.json();

                if (data.success === false) {
                    resultTable.innerHTML = '<div class="text-danger">' +
                        'Error: ' + data.raw +
                        '</div>';
                    console.error('Error:', data.raw);
                    // Hide loading spinner
                    loadingSpinner.classList.add('d-none');
                    return;
                }

                // Clear spinner
                resultTable.innerHTML = '';

                // Build table
                let thead = document.createElement('thead');
                let headerRow = document.createElement('tr');
                data.columns.forEach(col => {
                    let th = document.createElement('th');
                    th.innerText = col;
                    headerRow.appendChild(th);
                });

                thead.appendChild(headerRow);
                resultTable.appendChild(thead);

                let tbody = document.createElement('tbody');
                data.rows.forEach(row => {
                    let tr = document.createElement('tr');
                    data.columns.forEach(col => {
                        let td = document.createElement('td');
                        td.innerText = row[col];
                        tr.appendChild(td);
                    });
                    tbody.appendChild(tr);
                });

                resultTable.appendChild(tbody);
                // Hide loading spinner
                loadingSpinner.classList.add('d-none');

            } catch (error) {
                resultTable.innerHTML = '<div class="text-danger">' +
                    'Error: ' + error.message +
                    '</div>';
                console.error('Error:', error);
                // Hide loading spinner
                console.log(error);

                loadingSpinner.classList.add('d-none');
            }*/
        });

    </script>


</x-layouts.main>

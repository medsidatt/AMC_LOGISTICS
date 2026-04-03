<form action="{{ route('sharepoint.upload') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="file" required>
    <button type="submit">Upload to SharePoint</button>
</form>
@if(session('success'))
    <p>{{ session('success') }}</p>
@endif

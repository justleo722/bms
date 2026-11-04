<h1>BMS registration entry codes</h1>
<p>These six single-use entry codes can be given to customers who have purchased the system. Each code is invalidated after one successful registration and replaced by a new code emailed to you.</p>
<ul>
    @foreach ($codes as $code)
        <li><strong>{{ $code }}</strong></li>
    @endforeach
</ul>

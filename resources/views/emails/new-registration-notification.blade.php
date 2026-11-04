<h1>A new BMS account has registered</h1>
<p><strong>Name:</strong> {{ $user->name }}</p>
<p><strong>Business:</strong> {{ $user->business_name }}</p>
<p><strong>Email:</strong> {{ $user->email }}</p>
<p><strong>Phone:</strong> {{ $user->phone }}</p>
<p><strong>Entry code used (now invalid):</strong> {{ $usedEntryCode }}</p>
<p><strong>Replacement entry code:</strong> {{ $replacementEntryCode }}</p>
<p>The replacement code has been added to the active registration pool.</p>

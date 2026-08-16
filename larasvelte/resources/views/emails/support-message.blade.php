<h1>New support request</h1>

<p><strong>Name:</strong> {{ $user->name }}</p>
<p><strong>Email:</strong> {{ $user->email }}</p>

<h2>Message</h2>

<p>{!! nl2br(e($supportMessage)) !!}</p>

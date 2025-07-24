<div>
	<br />
	<p>-----------------------------------------------</p>
	<b>{{ $reviewerName }}</b>
	<p>Recommendation : {{ $recommendation }}</p>
	<br />
	@foreach ($reviewResponses as $key => $value)
		<p>{{$key}} : </p>
		<blockquote> {{ $value }} </blockquote>
	@endforeach
	<p>For Author :</p>
	<blockquote>{!! $reviewForAuthorEditor !!}</blockquote>
</div>
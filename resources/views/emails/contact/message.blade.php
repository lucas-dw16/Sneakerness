@component('mail::message')
# Nieuw contactformulier bericht

**Naam:** {{ $payload['name'] }}  
**E-mail:** {{ $payload['email'] }}  
**Onderwerp:** {{ $payload['subject'] }}

---
{!! nl2br(e($payload['message'])) !!}

@endcomponent
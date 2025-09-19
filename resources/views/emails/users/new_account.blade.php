<x-mail::message>
# Welkom {{ $name }}

Er is een account voor je aangemaakt op {{ config('app.name') }}.

**E-mailadres:** {{ $email }}  
**Tijdelijk wachtwoord:** {{ $plainPassword }}

Log direct in via de onderstaande knop en wijzig vervolgens je wachtwoord.

<x-mail::button :url="$loginUrl">
Inloggen
</x-mail::button>

Als de knop niet werkt kopieer dan deze link:
{{ $loginUrl }}

Bedankt,
{{ config('app.name') }}
</x-mail::message>

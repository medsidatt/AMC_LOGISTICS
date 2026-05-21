<p>Bonjour {{ $invitation->name ?? '' }},</p>

<p>Un compte vous a été créé sur <strong>AMC Logistics</strong> avec le rôle <strong>{{ $invitation->role_name }}</strong>.</p>

<p>Voici vos identifiants de connexion :</p>

<ul>
    <li><strong>Email :</strong> {{ $invitation->email }}</li>
    <li><strong>Mot de passe temporaire :</strong> <code>{{ $tempPassword }}</code></li>
</ul>

<p>Connectez-vous ici : <a href="{{ $loginUrl }}">{{ $loginUrl }}</a></p>

<p><em>Pour des raisons de sécurité, vous serez invité à choisir un nouveau mot de passe lors de votre première connexion.</em></p>

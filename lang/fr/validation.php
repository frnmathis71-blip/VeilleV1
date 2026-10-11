<?php

return [
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être un texte.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',
    'confirmed' => 'La confirmation de :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe actuel est incorrect.',
    'min' => ['string' => 'Le champ :attribute doit contenir au moins :min caractères.'],
    'max' => ['string' => 'Le champ :attribute ne doit pas dépasser :max caractères.'],
    'password' => [
        'letters' => 'Le mot de passe doit contenir des lettres.',
        'mixed' => 'Le mot de passe doit contenir des minuscules et des majuscules.',
        'numbers' => 'Le mot de passe doit contenir un chiffre.',
        'symbols' => 'Le mot de passe doit contenir un caractère spécial.',
        'uncompromised' => 'Ce mot de passe a été compromis. Choisissez-en un autre.',
    ],
    'attributes' => ['name' => 'nom', 'email' => 'adresse e-mail', 'password' => 'mot de passe'],
];

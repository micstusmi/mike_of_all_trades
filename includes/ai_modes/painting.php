<?php

return [
    'mode' => 'painting',
    'title' => "Mike's AI Painting Estimator",

    'welcome_message' =>
        "Hi! I'm Mike's AI Painting Estimator. I'll help prepare your painting quote by asking only the questions that matter. If you have plans or photos, you can upload them at any time.",

    'starter_questions' => [
        [
            'id' => 'customer_type',
            'question' => 'Which best describes you?',
            'type' => 'choice',
            'options' => [
                'Homeowner',
                'Property manager',
                'Builder',
                'Real estate agent',
                'Other'
            ]
        ],
        [
            'id' => 'project_type',
            'question' => 'Is this an interior, exterior, or both?',
            'type' => 'choice',
            'options' => [
                'Interior',
                'Exterior',
                'Both'
            ]
        ],
        [
            'id' => 'info_available',
            'question' => 'What information do you already have?',
            'type' => 'multi',
            'options' => [
                'I know the square metres',
                'I have building plans',
                'I have photos',
                'I have an existing quote',
                'I am not sure where to start'
            ]
        ],
        [
            'id' => 'property_stage',
            'question' => 'Is the property brand new, existing/repaint, or renovation?',
            'type' => 'choice',
            'options' => [
                'Brand new',
                'Existing home being repainted',
                'Renovation / extension',
                'Not sure'
            ]
        ],
        [
            'id' => 'paint_system',
            'question' => 'What paint system do you think is required?',
            'type' => 'choice',
            'options' => [
                'Undercoat + 2 top coats',
                'Top coats only',
                'Undercoat only',
                'Not sure'
            ]
        ],
        [
            'id' => 'surfaces',
            'question' => 'What would you like painted?',
            'type' => 'multi',
            'options' => [
                'Walls',
                'Ceilings',
                'Doors',
                'Door frames',
                'Window frames',
                'Skirting boards',
                'Architraves',
                'Cabinets / joinery',
                'Exterior walls',
                'Eaves',
                'Fascia',
                'Fence',
                'Deck / pergola',
                'Other'
            ]
        ],
        [
            'id' => 'condition',
            'question' => 'Are there any repairs or preparation issues?',
            'type' => 'multi',
            'options' => [
                'No obvious issues',
                'Small holes',
                'Cracks',
                'Plaster repairs needed',
                'Water damage',
                'Peeling / flaking paint',
                'Mould',
                'Wallpaper removal',
                'High ceilings',
                'Difficult access',
                'Furniture to move'
            ]
        ],
        [
            'id' => 'paint_supply',
            'question' => 'Who will supply the paint and materials?',
            'type' => 'choice',
            'options' => [
                'Customer supplies paint/materials',
                'Mike supplies paint/materials',
                'Not sure yet'
            ]
        ],
        [
            'id' => 'description',
            'question' => 'Please describe the job in your own words.',
            'type' => 'text'
        ]
    ]
];

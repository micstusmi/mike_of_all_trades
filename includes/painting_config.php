<?php
$paintingConfig = [
    // Fallback prices are used only when the customer chooses a rough size but has not entered quantities.
    'fallback_prices' => [
        'small' => ['tiny' => 250, 'small' => 350, 'medium' => 550, 'large' => 850, 'huge' => 1200],
        'one_room' => ['small' => 650, 'medium' => 900, 'large' => 1300, 'huge' => 1700],
        'multi_room' => ['small' => 1500, 'medium' => 2600, 'large' => 4200, 'huge' => 6000],
        'whole_interior' => ['small' => 3200, 'medium' => 5200, 'large' => 7800, 'huge' => 10500],
        'exterior' => ['small' => 1800, 'medium' => 4500, 'large' => 7500, 'huge' => 11000]
    ],

    // Labour production rates. These are deliberately additive, not multiplicative.
    // Tune these over time against your real jobs.
    'rates' => [
        'interior_walls_m2' => 11,
        'ceilings_m2' => 12,
        'exterior_walls_m2' => 38,
        'exterior_walls_m2_budget' => 28,
        'exterior_walls_m2_premium' => 52,
        'trim_linear_m' => 9,
        'eaves_linear_m' => 18,
        'fascia_linear_m' => 16,
        'door_each' => 90,
        'window_frame_each' => 120,
        'exterior_window_frame_each' => 135,
        'robe_each' => 160,
        'room_each' => 140,
        'hallway_each' => 180,
        'wash_light' => 250,
        'wash_heavy' => 650,
        'repair_item' => 180
    ],

    'finish_adjustments' => [
        'budget' => -0.12,
        'standard' => 0,
        'premium' => 0.22
    ],

    'condition_adders' => [
        'good' => 0,
        'average' => 0.10,
        'rough' => 0.25
    ],

    'access_adders' => [
        'easy' => 0,
        'normal' => 0.06,
        'hard' => 0.18,
        'scaffold' => 0.35
    ],

    'minimum_labour' => 250,
    'materials_percent_interior' => 0.18,
    'materials_percent_exterior' => 0.20,
    'minimum_materials' => 250
];

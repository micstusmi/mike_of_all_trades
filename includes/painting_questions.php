<?php
$paintingQuestions = [
    [
        'id'=>'job_type','type'=>'choice','question'=>'What type of painting job do you need?','modes'=>['quick','detailed','precise'],
        'options'=>[
            ['value'=>'small','label'=>'Small job / touch-up','help'=>'One wall, one ceiling, patch repair, door, or small area.'],
            ['value'=>'one_room','label'=>'One room','help'=>'Bedroom, lounge, office, kitchen, laundry, etc.'],
            ['value'=>'multi_room','label'=>'Multiple rooms','help'=>'Several rooms but not the whole house.'],
            ['value'=>'whole_interior','label'=>'Whole interior','help'=>'Most or all internal areas of the home.'],
            ['value'=>'exterior','label'=>'Exterior painting','help'=>'Outside walls, eaves, fascia, trims, fence, deck, etc.']
        ]
    ],

    // QUICK SIZE QUESTIONS - wording changes by job type
    ['id'=>'size_small','save_as'=>'job_size','type'=>'choice','question'=>'Roughly how big is the small job?','modes'=>['quick','detailed','precise'],'show_if'=>['job_type'=>'small'],'options'=>[
        ['value'=>'tiny','label'=>'Tiny','help'=>'Very small touch-up or patch.'],['value'=>'small','label'=>'Small','help'=>'One wall, one door, or one ceiling.'],['value'=>'medium','label'=>'Medium','help'=>'Several small items.'],['value'=>'large','label'=>'Larger small job','help'=>'Multiple walls/items or more involved repair.']]],
    ['id'=>'size_room','save_as'=>'job_size','type'=>'choice','question'=>'Roughly how big is the room?','modes'=>['quick','detailed','precise'],'show_if'=>['job_type'=>'one_room'],'options'=>[
        ['value'=>'small','label'=>'Small room','help'=>'Small bedroom, study or laundry.'],['value'=>'medium','label'=>'Average room','help'=>'Normal bedroom, kitchen or lounge.'],['value'=>'large','label'=>'Large room','help'=>'Large lounge/open-plan area.'],['value'=>'huge','label'=>'Huge / high ceiling room','help'=>'Very large room, high ceilings or difficult scope.']]],
    ['id'=>'size_multi','save_as'=>'job_size','type'=>'choice','question'=>'Roughly how many rooms are included?','modes'=>['quick','detailed','precise'],'show_if'=>['job_type'=>'multi_room'],'options'=>[
        ['value'=>'small','label'=>'2 rooms','help'=>'Two smaller rooms.'],['value'=>'medium','label'=>'3–4 rooms','help'=>'Several average rooms.'],['value'=>'large','label'=>'5–6 rooms','help'=>'Large multi-room job.'],['value'=>'huge','label'=>'7+ rooms','help'=>'Almost a whole interior.']]],
    ['id'=>'size_interior','save_as'=>'job_size','type'=>'choice','question'=>'Roughly what size is the home?','modes'=>['quick','detailed','precise'],'show_if'=>['job_type'=>'whole_interior'],'options'=>[
        ['value'=>'small','label'=>'Small unit / apartment','help'=>'Studio, unit, small 2-bedroom home.'],['value'=>'medium','label'=>'Medium house','help'=>'Typical 3-bedroom home or larger unit.'],['value'=>'large','label'=>'Large house','help'=>'4-bedroom home or large open-plan areas.'],['value'=>'huge','label'=>'Huge house','help'=>'5+ bedrooms, large home, or complex layout.']]],
    ['id'=>'size_exterior','save_as'=>'job_size','type'=>'choice','question'=>'Roughly how big is the exterior job?','modes'=>['quick','detailed','precise'],'show_if'=>['job_type'=>'exterior'],'options'=>[
        ['value'=>'small','label'=>'Small exterior section','help'=>'Front only, trims only, fence, deck, or one small area.'],['value'=>'medium','label'=>'Average single-storey exterior','help'=>'Typical single-storey exterior job.'],['value'=>'large','label'=>'Large exterior','help'=>'Large single storey, double storey, or multiple exterior areas.'],['value'=>'huge','label'=>'Huge / difficult exterior','help'=>'Large double-storey, difficult access, or major prep.']]],

    ['id'=>'finish_level','type'=>'choice','question'=>'What finish level would you like?','modes'=>['quick','detailed','precise'],'options'=>[
        ['value'=>'budget','label'=>'Budget refresh','help'=>'Minimal preparation. Best for quick improvements or rentals.'],
        ['value'=>'standard','label'=>'Standard repaint','help'=>'Normal preparation, basic filling/sanding, good finish.'],
        ['value'=>'premium','label'=>'Premium finish','help'=>'More detailed prep, patching, sanding, and undercoat where needed.']]],

    ['id'=>'interior_surfaces','save_as'=>'surfaces','type'=>'multi','question'=>'What interior parts should be painted?','modes'=>['quick','detailed','precise'],'show_if'=>['job_type'=>['small','one_room','multi_room','whole_interior']],'options'=>[
        ['value'=>'walls','label'=>'Walls'],['value'=>'ceilings','label'=>'Ceilings'],['value'=>'skirting','label'=>'Skirting boards'],['value'=>'architraves','label'=>'Architraves'],['value'=>'doors','label'=>'Doors'],['value'=>'window_frames','label'=>'Window frames'],['value'=>'robes','label'=>'Inside robes']]],
    ['id'=>'exterior_surfaces','save_as'=>'surfaces','type'=>'multi','question'=>'What exterior parts should be painted?','modes'=>['quick','detailed','precise'],'show_if'=>['job_type'=>'exterior'],'options'=>[
        ['value'=>'exterior_walls','label'=>'Exterior walls/siding/render'],['value'=>'eaves','label'=>'Eaves'],['value'=>'fascia','label'=>'Fascia/barge boards'],['value'=>'gutters','label'=>'Gutters/downpipes'],['value'=>'window_frames','label'=>'Exterior window frames'],['value'=>'doors','label'=>'Exterior doors'],['value'=>'pergola_deck','label'=>'Pergola/deck/verandah'],['value'=>'fence','label'=>'Fence']]],

    ['id'=>'condition','type'=>'choice','question'=>'What condition are the surfaces in?','modes'=>['quick','detailed','precise'],'options'=>[
        ['value'=>'good','label'=>'Good','help'=>'Clean surfaces, only minor marks.'],['value'=>'average','label'=>'Average','help'=>'Some patching, sanding, cracks or paint issues.'],['value'=>'rough','label'=>'Rough','help'=>'Peeling, stains, water damage, mould, or lots of repairs.']]],
    ['id'=>'access','type'=>'choice','question'=>'Is access easy?','modes'=>['quick','detailed','precise'],'options'=>[
        ['value'=>'easy','label'=>'Easy','help'=>'Empty/easy areas or easy exterior access.'],['value'=>'normal','label'=>'Normal','help'=>'Some furniture, normal ladders, normal working conditions.'],['value'=>'hard','label'=>'Difficult','help'=>'High ceilings, tight spaces, slopes, or difficult ladder work.'],['value'=>'scaffold','label'=>'May need scaffold/platform','help'=>'High or difficult exterior access.']]],
    ['id'=>'paint_supply','type'=>'choice','question'=>'Who will supply paint and materials?','modes'=>['quick','detailed','precise'],'options'=>[
        ['value'=>'customer','label'=>'Customer supplies paint/materials','help'=>'Labour-only estimate.'],['value'=>'mike','label'=>'Mike supplies paint/materials','help'=>'Estimated paint/materials added for possible prepayment.']]],

    // DETAILED INTERIOR
    ['id'=>'bedrooms','type'=>'number','question'=>'How many bedrooms are included?','modes'=>['detailed','precise'],'show_if'=>['job_type'=>['whole_interior','multi_room']]],
    ['id'=>'living_areas','type'=>'number','question'=>'How many living/dining areas are included?','modes'=>['detailed','precise'],'show_if'=>['job_type'=>['whole_interior','multi_room']]],
    ['id'=>'wet_areas','type'=>'number','question'=>'How many bathrooms/laundries/wet areas are included?','modes'=>['detailed','precise'],'show_if'=>['job_type'=>['whole_interior','multi_room']]],
    ['id'=>'furnished','type'=>'choice','question'=>'Will the interior be empty or furnished?','modes'=>['detailed','precise'],'show_if'=>['job_type'=>['one_room','multi_room','whole_interior']],'options'=>[
        ['value'=>'empty','label'=>'Empty'],['value'=>'partly_furnished','label'=>'Partly furnished'],['value'=>'furnished','label'=>'Furnished']]],
    ['id'=>'colour_change','type'=>'choice','question'=>'Is there a major colour change?','modes'=>['detailed','precise'],'options'=>[
        ['value'=>'similar','label'=>'Similar colour'],['value'=>'light_to_dark','label'=>'Light to dark'],['value'=>'dark_to_light','label'=>'Dark to light'],['value'=>'unsure','label'=>'Unsure']]],
    ['id'=>'ceiling_height','type'=>'choice','question'=>'What are the ceiling heights?','modes'=>['detailed','precise'],'show_if'=>['job_type'=>['one_room','multi_room','whole_interior']],'options'=>[
        ['value'=>'normal','label'=>'Normal approx 2.4m'],['value'=>'high','label'=>'High ceilings'],['value'=>'mixed','label'=>'Mixed / unsure']]],

    // DETAILED EXTERIOR
    ['id'=>'storeys','type'=>'choice','question'=>'How many storeys is the exterior?','modes'=>['detailed','precise'],'show_if'=>['job_type'=>'exterior'],'options'=>[
        ['value'=>'single','label'=>'Single storey'],['value'=>'double','label'=>'Double storey'],['value'=>'split','label'=>'Split level / mixed'],['value'=>'other','label'=>'Other / unsure']]],
    ['id'=>'exterior_surface_type','type'=>'choice','question'=>'What is the main exterior surface?','modes'=>['detailed','precise'],'show_if'=>['job_type'=>'exterior'],'options'=>[
        ['value'=>'weatherboard','label'=>'Weatherboard/timber'],['value'=>'render','label'=>'Render'],['value'=>'brick','label'=>'Painted brick'],['value'=>'cladding','label'=>'Cladding'],['value'=>'mixed','label'=>'Mixed / unsure']]],
    ['id'=>'exterior_scope','type'=>'choice','question'=>'How much of the exterior is included?','modes'=>['detailed','precise'],'show_if'=>['job_type'=>'exterior'],'options'=>[
        ['value'=>'front_only','label'=>'Front only'],['value'=>'one_side','label'=>'One side/section only'],['value'=>'whole_house','label'=>'Whole exterior'],['value'=>'trim_only','label'=>'Trims only'],['value'=>'unsure','label'=>'Unsure']]],
    ['id'=>'exterior_peeling','type'=>'choice','question'=>'Is there peeling/flaking paint outside?','modes'=>['detailed','precise'],'show_if'=>['job_type'=>'exterior'],'options'=>[
        ['value'=>'none','label'=>'No / minor'],['value'=>'some','label'=>'Some peeling'],['value'=>'lots','label'=>'Lots of peeling'],['value'=>'unsure','label'=>'Unsure']]],

    // PRECISE INTERIOR
    ['id'=>'floor_area_m2','type'=>'number','question'=>'Approximate internal floor area in square metres?','modes'=>['precise'],'show_if'=>['job_type'=>['whole_interior','multi_room']],'placeholder'=>'Example: 165'],
    ['id'=>'wall_area_m2','type'=>'number','question'=>'Approximate wall area in square metres if known?','modes'=>['precise'],'show_if'=>['job_type'=>['small','one_room','multi_room','whole_interior']],'placeholder'=>'Optional best guess'],
    ['id'=>'room_count','type'=>'number','question'=>'Total number of rooms/areas included?','modes'=>['precise'],'show_if'=>['job_type'=>['multi_room','whole_interior']]],
    ['id'=>'hallways','type'=>'number','question'=>'How many hallways or entry areas are included?','modes'=>['precise'],'show_if'=>['job_type'=>['multi_room','whole_interior']]],
    ['id'=>'doors_count','type'=>'number','question'=>'How many doors need painting?','modes'=>['precise'],'show_if'=>['job_type'=>['one_room','multi_room','whole_interior','small']]],
    ['id'=>'window_frames_count','type'=>'number','question'=>'How many window frames need painting?','modes'=>['precise'],'show_if'=>['job_type'=>['one_room','multi_room','whole_interior','small','exterior']]],
    ['id'=>'robes_count','type'=>'number','question'=>'How many built-in or walk-in robes are included?','modes'=>['precise'],'show_if'=>['job_type'=>['one_room','multi_room','whole_interior']]],
    ['id'=>'repairs','type'=>'multi','question'=>'What repairs or surface issues are present?','modes'=>['precise'],'options'=>[
        ['value'=>'small_holes','label'=>'Small holes'],['value'=>'cracks','label'=>'Cracks'],['value'=>'water_damage','label'=>'Water damage'],['value'=>'stains','label'=>'Stains'],['value'=>'peeling','label'=>'Peeling paint'],['value'=>'mould','label'=>'Mould'],['value'=>'wallpaper','label'=>'Wallpaper removal'],['value'=>'none','label'=>'None / minor only']]],

    // PRECISE EXTERIOR
    ['id'=>'exterior_wall_m2','type'=>'number','question'=>'Approximate exterior wall/surface area in square metres if known?','modes'=>['precise'],'show_if'=>['job_type'=>'exterior'],'placeholder'=>'Optional best guess'],
    ['id'=>'linear_trim_m','type'=>'number','question'=>'Approximate metres of fascia/eaves/trim if known?','modes'=>['precise'],'show_if'=>['job_type'=>'exterior'],'placeholder'=>'Optional best guess'],
    ['id'=>'ground_slope','type'=>'choice','question'=>'Is the ground around the house flat or sloped?','modes'=>['precise'],'show_if'=>['job_type'=>'exterior'],'options'=>[
        ['value'=>'flat','label'=>'Mostly flat'],['value'=>'some_slope','label'=>'Some slope'],['value'=>'steep','label'=>'Steep/difficult'],['value'=>'unsure','label'=>'Unsure']]],
    ['id'=>'washing_needed','type'=>'choice','question'=>'Will the exterior need washing/cleaning before painting?','modes'=>['precise'],'show_if'=>['job_type'=>'exterior'],'options'=>[
        ['value'=>'no','label'=>'No / already clean'],['value'=>'light','label'=>'Light wash'],['value'=>'heavy','label'=>'Heavy wash/mould/dirt'],['value'=>'unsure','label'=>'Unsure']]],

    ['id'=>'photos_available','type'=>'choice','question'=>'Can you upload photos or a floor plan?','modes'=>['detailed','precise'],'options'=>[
        ['value'=>'yes','label'=>'Yes'],['value'=>'later','label'=>'Later'],['value'=>'no','label'=>'No']]],
    ['id'=>'notes','type'=>'text','question'=>'Anything else Mike should know?','modes'=>['detailed','precise'],'placeholder'=>'Example: vacant house, dark walls to white, peeling exterior paint, access issues, etc.']
];

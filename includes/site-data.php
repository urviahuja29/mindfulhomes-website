<?php
declare(strict_types=1);

return [
    'brand' => [
        'name' => 'The Mindful Homes',
        'logo' => '/assets/images/logo.png',
        'logo_alt' => 'The Mindful Homes logo',
    ],
    'nav' => [
        ['label' => 'Home', 'href' => '#home'],
        ['label' => 'About', 'href' => '#services'],
        ['label' => 'Contact', 'href' => '#contact'],
    ],
    'hero' => [
        'title' => 'Creating spaces with purpose',
        'description' => 'Declutter your home and embrace a stress-free lifestyle today.',
        'image' => '/assets/images/hero-kitchen.png',
        'primary_cta' => ['label' => 'Book consultation', 'href' => '#contact'],
        'secondary_cta' => ['label' => 'Organize yourself', 'href' => '#organizer'],
    ],
    'services_intro' => [
        'heading' => 'Our design services',
        'description' => 'Discover tailored solutions for decluttering and organizing your living spaces effortlessly.',
        'cta' => ['label' => 'Book free consultation', 'href' => '#contact'],
    ],
    'services' => [
        [
            'title' => 'Home Decluttering & Organization',
            'description' => 'We help create a personalized, clutter-free space that works for you. Let go of the excess, streamline your life, and maintain lasting systems with ease.',
            'image' => '/assets/images/service-organizer.jpeg',
            'image_alt' => 'Professional organizer styling shelves in a tidy family space.',
        ],
        [
            'title' => 'Senior Transition Support',
            'description' => 'We ease the emotional and physical challenges of downsizing, relocating, or reorganizing a longtime home. Simplify the transition with our support and gain order, function, and peace of mind.',
            'image' => '/assets/images/organizer-room.png',
            'image_alt' => 'Calm living room styled in soft neutral tones.',
        ],
        [
            'title' => 'Downsizing and & Management',
            'description' => 'From packing to organizing your new space, we’re with you every step. Moving is the perfect time to create systems that keep your new home organized and stress-free.',
            'image' => '/assets/images/service-moving.png',
            'image_alt' => 'Packed moving boxes in an organized room ready for a transition.',
        ],
    ],
    'process_intro' => [
        'heading' => 'Our Process',
        'description' => 'We create tailored plans for your space through consultation, design, and organization.',
        'image' => '/assets/images/organizer-room.png',
        'image_alt' => 'Thoughtfully organized room with layered neutral decor.',
    ],
    'process' => [
        [
            'step' => '01',
            'title' => 'In-Home Consultation',
            'description' => 'We assess your needs, timeline, and budget to create a personalized organizational plan for your space.',
        ],
        [
            'step' => '02',
            'title' => 'Moodboard & Product Procurement',
            'description' => 'We craft moodboards and sketches to visualize your design and source products as needed, complimentary with our service.',
        ],
        [
            'step' => '03',
            'title' => 'Space Transformation',
            'description' => 'We organize and optimize your space according to the plan. The process concludes with a walk-through to ensure complete satisfaction.',
        ],
    ],
    'organizer_promo' => [
        'heading' => 'Organize yourself',
        'description' => 'If you want a gentle starting point before booking, upload a few photos and receive an AI-assisted organizing plan for your own space.',
        'image' => '/assets/images/organizer-room.png',
        'image_alt' => 'Organized room styled with soft neutral storage solutions.',
        'space_type_options' => [
            'pantry' => 'Pantry',
            'closet' => 'Closet',
            'playroom' => 'Playroom',
            'kitchen' => 'Kitchen',
            'office' => 'Office',
            'other' => 'Other',
        ],
        'goal_options' => [
            'quick reset' => 'Quick reset',
            'full system' => 'Full system',
            'moving' => 'Moving',
            'downsizing' => 'Downsizing',
        ],
    ],
    'faq_intro' => [
        'heading' => 'Frequently asked questions',
        'description' => 'A few answers to help you understand what working together can look like.',
    ],
    'faq' => [
        [
            'question' => 'How long will my project take?',
            'answer' => 'Project length depends on the size of the space, how much editing is needed, and how quickly decisions are made during the process.',
        ],
        [
            'question' => 'How much do organization products cost?',
            'answer' => 'Product costs vary by space, but we always work within your budget and only recommend pieces that genuinely support the system.',
        ],
        [
            'question' => 'Do I buy all the organizing products?',
            'answer' => 'No. We can source products for you, coordinate ordering, and manage returns when something is not the right fit.',
        ],
        [
            'question' => 'Do I need to be present while you are organizing?',
            'answer' => 'Not necessarily. Some clients stay involved while others step away; we tailor the process to your comfort level and project needs.',
        ],
        [
            'question' => 'How do we get started?',
            'answer' => 'Reach out through the contact form, phone, or email and we will schedule a consultation to understand your space and priorities.',
        ],
        [
            'question' => 'I\'m concerned about maintaining the space after you\'re gone.',
            'answer' => 'Our systems are designed around your habits and routines so they feel realistic to maintain, not just beautiful on day one.',
        ],
    ],
    'contact' => [
        'heading' => 'Let\'s connect',
        'description' => 'Share a little about your space, timeline, and goals. We would love to hear your questions and talk through next steps.',
        'phone' => '818-601-2015',
        'email' => 'themindfulhomes@gmail.com',
        'form' => [
            'first_name_label' => 'Name',
            'last_name_label' => 'Last name',
            'email_label' => 'Email',
            'message_label' => 'Message',
            'submit_label' => 'Submit',
            'status_note' => 'Reach out by phone or email for now while the contact form is being connected.',
        ],
    ],
    'instagram' => [
        'heading' => 'Our Instagram',
        'description' => 'A glimpse at thoughtful, real-life spaces we have helped transform.',
        'cta' => ['label' => 'Book consultation', 'href' => '#contact'],
        'items' => [
            [
                'image' => '/assets/images/service-playroom.webp',
                'alt' => 'Playroom shelving styled with baskets and books.',
            ],
            [
                'image' => '/assets/images/service-moving.png',
                'alt' => 'Moving boxes neatly staged in a bright room.',
            ],
            [
                'image' => '/assets/images/service-organizer.jpeg',
                'alt' => 'Organizer arranging children\'s shelving.',
            ],
            [
                'image' => '/assets/images/hero-kitchen.png',
                'alt' => 'Calm kitchen with organized counters and open light.',
            ],
        ],
    ],
];

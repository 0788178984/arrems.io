<?php
require_once '../backend_PHP_files/config.php';

try {
    // Insert sample users
    $users = [
        [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'agent1@example.com',
            'password' => password_hash('agent123', PASSWORD_DEFAULT),
            'phone' => '+1234567890',
            'role' => 'agent',
            'status' => 'active'
        ],
        [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'agent2@example.com',
            'password' => password_hash('agent123', PASSWORD_DEFAULT),
            'phone' => '+1234567891',
            'role' => 'agent',
            'status' => 'active'
        ],
        [
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'phone' => '+1234567892',
            'role' => 'admin',
            'status' => 'active'
        ],
        [
            'first_name' => 'Mike',
            'last_name' => 'Manager',
            'email' => 'manager@example.com',
            'password' => password_hash('manager123', PASSWORD_DEFAULT),
            'phone' => '+1234567893',
            'role' => 'manager',
            'status' => 'active'
        ],
        [
            'first_name' => 'Bob',
            'last_name' => 'Buyer',
            'email' => 'buyer@example.com',
            'password' => password_hash('buyer123', PASSWORD_DEFAULT),
            'phone' => '+1234567894',
            'role' => 'buyer',
            'status' => 'active'
        ],
        [
            'first_name' => 'Sarah',
            'last_name' => 'Seller',
            'email' => 'seller@example.com',
            'password' => password_hash('seller123', PASSWORD_DEFAULT),
            'phone' => '+1234567895',
            'role' => 'seller',
            'status' => 'active'
        ],
        [
            'first_name' => 'Tom',
            'last_name' => 'Client',
            'email' => 'client@example.com',
            'password' => password_hash('client123', PASSWORD_DEFAULT),
            'phone' => '+1234567896',
            'role' => 'client',
            'status' => 'active'
        ],
        [
            'first_name' => 'Steve',
            'last_name' => 'Stakeholder',
            'email' => 'stakeholder@example.com',
            'password' => password_hash('stake123', PASSWORD_DEFAULT),
            'phone' => '+1234567897',
            'role' => 'stakeholder',
            'status' => 'active'
        ]
    ];

    $sql = "
        INSERT INTO users (
            first_name, 
            last_name, 
            email, 
            password, 
            phone,
            role,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ";
    echo "SQL Query: " . $sql . "\n";
    $stmt = $pdo->prepare($sql);

    foreach ($users as $user) {
        $params = [
            $user['first_name'],
            $user['last_name'],
            $user['email'],
            $user['password'],
            $user['phone'],
            $user['role'],
            $user['status']
        ];
        echo "Parameters: " . print_r($params, true) . "\n";
        $stmt->execute($params);
    }

    // Get agent IDs for properties
    $agent1Id = $pdo->query("SELECT id FROM users WHERE email = 'agent1@example.com'")->fetch(PDO::FETCH_COLUMN);
    $agent2Id = $pdo->query("SELECT id FROM users WHERE email = 'agent2@example.com'")->fetch(PDO::FETCH_COLUMN);

    // Insert sample properties
    $properties = [
        [
            'title' => 'Modern Beachfront Villa',
            'description' => 'Luxurious 4-bedroom villa with direct beach access and panoramic ocean views.',
            'type' => 'house',
            'status' => 'available',
            'price' => 1200000.00,
            'area_size' => 350.00,
            'bedrooms' => 4,
            'bathrooms' => 3,
            'address' => '123 Coastal Drive',
            'city' => 'Miami Beach',
            'state' => 'Florida',
            'zip_code' => '33139',
            'latitude' => 25.7617,
            'longitude' => -80.1918,
            'agent_id' => $agent1Id
        ],
        [
            'title' => 'Downtown Luxury Apartment',
            'description' => 'High-end 2-bedroom apartment in the heart of the city with stunning skyline views.',
            'type' => 'apartment',
            'status' => 'available',
            'price' => 750000.00,
            'area_size' => 120.00,
            'bedrooms' => 2,
            'bathrooms' => 2,
            'address' => '456 City Center Ave',
            'city' => 'Miami',
            'state' => 'Florida',
            'zip_code' => '33130',
            'latitude' => 25.7617,
            'longitude' => -80.1918,
            'agent_id' => $agent2Id
        ],
        [
            'title' => 'Commercial Office Space',
            'description' => 'Prime location commercial space perfect for business headquarters.',
            'type' => 'commercial',
            'status' => 'available',
            'price' => 2000000.00,
            'area_size' => 500.00,
            'bedrooms' => null,
            'bathrooms' => 4,
            'address' => '789 Business District',
            'city' => 'Miami',
            'state' => 'Florida',
            'zip_code' => '33131',
            'latitude' => 25.7617,
            'longitude' => -80.1918,
            'agent_id' => $agent1Id
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO properties (title, description, type, status, price, area_size, bedrooms, bathrooms, 
                              address, city, state, zip_code, latitude, longitude, agent_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($properties as $property) {
        $stmt->execute([
            $property['title'],
            $property['description'],
            $property['type'],
            $property['status'],
            $property['price'],
            $property['area_size'],
            $property['bedrooms'],
            $property['bathrooms'],
            $property['address'],
            $property['city'],
            $property['state'],
            $property['zip_code'],
            $property['latitude'],
            $property['longitude'],
            $property['agent_id']
        ]);
        $propertyId = $pdo->lastInsertId();

        // Add property features
        $features = [
            ['Swimming Pool', 'Yes'],
            ['Parking', '2 Cars'],
            ['Air Conditioning', 'Central'],
            ['Security System', 'Advanced'],
            ['Year Built', '2020']
        ];

        $stmt = $pdo->prepare("
            INSERT INTO property_features (property_id, feature_name, feature_value)
            VALUES (?, ?, ?)
        ");

        foreach ($features as $feature) {
            $stmt->execute([$propertyId, $feature[0], $feature[1]]);
        }

        // Add property media
        $mediaItems = [
            ['image', 'sample_house_1.jpg', 'Front View', true],
            ['image', 'sample_house_2.jpg', 'Living Room', false],
            ['3d_model', 'house_model.glb', '3D Tour', false],
            ['video', 'property_tour.mp4', 'Video Tour', false]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO property_media (property_id, media_type, file_path, title, is_primary)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($mediaItems as $media) {
            $stmt->execute([$propertyId, $media[0], $media[1], $media[2], $media[3]]);
        }

        // Add some analytics data
        $stmt = $pdo->prepare("
            INSERT INTO property_analytics (property_id, views_count, ar_views_count, favorite_count)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$propertyId, rand(50, 200), rand(10, 50), rand(5, 20)]);
    }

    // Add some sample reviews
    $reviews = [
        ['rating' => 5, 'comment' => 'Excellent property, exactly as described!'],
        ['rating' => 4, 'comment' => 'Great location and amenities.'],
        ['rating' => 5, 'comment' => 'The virtual tour was very helpful in making our decision.']
    ];

    $stmt = $pdo->prepare("
        INSERT INTO reviews (property_id, user_id, rating, comment)
        VALUES (?, ?, ?, ?)
    ");

    $properties = $pdo->query("SELECT id FROM properties")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($properties as $propertyId) {
        foreach ($reviews as $review) {
            $stmt->execute([
                $propertyId,
                $agent1Id, // Using agent as reviewer for demo
                $review['rating'],
                $review['comment']
            ]);
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Sample data has been successfully inserted into the database.'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
} 
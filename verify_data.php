<?php
require_once 'backend_PHP_files/config.php';

try {
    // Check users
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "Users count: " . $userCount . "\n";

    // Check properties
    $propertyCount = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
    echo "Properties count: " . $propertyCount . "\n";

    // Check property media
    $mediaCount = $pdo->query("SELECT COUNT(*) FROM property_media")->fetchColumn();
    echo "Property media count: " . $mediaCount . "\n";

    // Check property features
    $featureCount = $pdo->query("SELECT COUNT(*) FROM property_features")->fetchColumn();
    echo "Property features count: " . $featureCount . "\n";

    // Check reviews
    $reviewCount = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
    echo "Reviews count: " . $reviewCount . "\n";

    // Check analytics
    $analyticsCount = $pdo->query("SELECT COUNT(*) FROM property_analytics")->fetchColumn();
    echo "Property analytics count: " . $analyticsCount . "\n";

    // Sample property data
    $properties = $pdo->query("SELECT * FROM properties LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    echo "\nSample property:\n";
    print_r($properties);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 
<?php

/**
 * Property Model
 */

class Property
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Create a new property
     */
    public function create($landlordId, $data)
    {
        $stmt = $this->conn->prepare("
            INSERT INTO properties (
                landlord_id, name, description, address, city, zipcode,
                latitude, longitude, property_type, bedrooms, bathrooms,
                square_feet, furnished, price_per_month, availability_date,
                max_occupants, amenities, rules, image_urls
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $amenities = json_encode($data['amenities'] ?? []);
        $rules = json_encode($data['rules'] ?? []);
        $imageUrls = json_encode($data['image_urls'] ?? []);

        $stmt->bind_param(
            "isssssddsidiidsisss",
            $landlordId,
            $data['name'],
            $data['description'],
            $data['address'],
            $data['city'],
            $data['zipcode'],
            $data['latitude'],
            $data['longitude'],
            $data['property_type'],
            $data['bedrooms'],
            $data['bathrooms'],
            $data['square_feet'],
            $data['furnished'],
            $data['price_per_month'],
            $data['availability_date'],
            $data['max_occupants'],
            $amenities,
            $rules,
            $imageUrls
        );

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Property created', 'property_id' => $this->conn->insert_id];
        }

        return ['success' => false, 'message' => 'Failed to create property'];
    }

    /**
     * Get property by ID
     */
    public function getById($propertyId)
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, 
                   u.first_name as landlord_first_name,
                   u.last_name as landlord_last_name,
                   u.email as landlord_email,
                   u.profile_picture as landlord_profile_picture,
                   lp.rating as landlord_rating,
                   COUNT(DISTINCT r.id) as review_count,
                   AVG(r.rating) as avg_rating,
                   COUNT(DISTINCT active_b.id) as active_booking_count
            FROM properties p
            LEFT JOIN users u ON p.landlord_id = u.id
            LEFT JOIN landlord_profiles lp ON lp.user_id = u.id
            LEFT JOIN reviews r ON r.property_id = p.id
            LEFT JOIN bookings active_b ON active_b.property_id = p.id AND active_b.status IN ('pending', 'confirmed')
            WHERE p.id = ?
            GROUP BY p.id
        ");
        $stmt->bind_param("i", $propertyId);
        $stmt->execute();
        $property = $stmt->get_result()->fetch_assoc();

        if ($property) {
            $property['amenities'] = json_decode($property['amenities'] ?? '[]', true);
            $property['rules'] = json_decode($property['rules'] ?? '[]', true);
            $property['image_urls'] = json_decode($property['image_urls'] ?? '[]', true);
        }

        return $property;
    }

    /**
     * List properties with filters
     */
    public function listProperties($filters = [], $limit = 10, $offset = 0)
    {
        $query = "
            SELECT p.*, 
                   u.first_name as landlord_first_name,
                   u.last_name as landlord_last_name,
                   COUNT(DISTINCT r.id) as review_count,
                   AVG(r.rating) as avg_rating,
                   COUNT(DISTINCT active_b.id) as active_booking_count
            FROM properties p
            LEFT JOIN users u ON p.landlord_id = u.id
            LEFT JOIN reviews r ON r.property_id = p.id
            LEFT JOIN bookings active_b ON active_b.property_id = p.id AND active_b.status IN ('pending', 'confirmed')
            WHERE p.verification_status = 'verified'
        ";

        $params = [];
        $types = '';

        if (!empty($filters['city'])) {
            $query .= " AND p.city = ?";
            $params[] = $filters['city'];
            $types .= 's';
        }

        if (!empty($filters['property_type'])) {
            $query .= " AND p.property_type = ?";
            $params[] = $filters['property_type'];
            $types .= 's';
        }

        if (!empty($filters['min_price'])) {
            $query .= " AND p.price_per_month >= ?";
            $params[] = $filters['min_price'];
            $types .= 'd';
        }

        if (!empty($filters['max_price'])) {
            $query .= " AND p.price_per_month <= ?";
            $params[] = $filters['max_price'];
            $types .= 'd';
        }

        if (!empty($filters['bedrooms'])) {
            $query .= " AND p.bedrooms >= ?";
            $params[] = $filters['bedrooms'];
            $types .= 'i';
        }

        $query .= " GROUP BY p.id ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->conn->prepare($query);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Decode JSON fields
        foreach ($results as &$property) {
            $property['amenities'] = json_decode($property['amenities'] ?? '[]', true);
            $property['rules'] = json_decode($property['rules'] ?? '[]', true);
            $property['image_urls'] = json_decode($property['image_urls'] ?? '[]', true);
        }

        return $results;
    }

    /**
     * Update property
     */
    public function update($propertyId, $landlordId, $data)
    {
        $updates = [];
        $params = [];
        $types = '';

        // Check ownership
        $stmt = $this->conn->prepare("SELECT landlord_id FROM properties WHERE id = ?");
        $stmt->bind_param("i", $propertyId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (!$result || $result['landlord_id'] != $landlordId) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        // Build update query
        foreach ($data as $key => $value) {
            if (in_array($key, ['name', 'description', 'address', 'city', 'zipcode', 'property_type', 'furnished'])) {
                $updates[] = "$key = ?";
                $params[] = $value;
                $types .= 's';
            } elseif (in_array($key, ['bedrooms', 'square_feet', 'max_occupants'])) {
                $updates[] = "$key = ?";
                $params[] = $value;
                $types .= 'i';
            } elseif (in_array($key, ['latitude', 'longitude', 'price_per_month', 'bathrooms'])) {
                $updates[] = "$key = ?";
                $params[] = $value;
                $types .= 'd';
            } elseif ($key === 'amenities' || $key === 'rules' || $key === 'image_urls') {
                $updates[] = "$key = ?";
                $params[] = json_encode($value);
                $types .= 's';
            }
        }

        if (empty($updates)) {
            return ['success' => false, 'message' => 'No valid fields to update'];
        }

        $params[] = $propertyId;
        $types .= 'i';

        $query = "UPDATE properties SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Property updated'];
        }

        return ['success' => false, 'message' => 'Update failed'];
    }

    /**
     * Delete property
     */
    public function delete($propertyId, $landlordId)
    {
        // Check ownership
        $stmt = $this->conn->prepare("SELECT landlord_id FROM properties WHERE id = ?");
        $stmt->bind_param("i", $propertyId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (!$result || $result['landlord_id'] != $landlordId) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $stmt = $this->conn->prepare("DELETE FROM properties WHERE id = ?");
        $stmt->bind_param("i", $propertyId);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Property deleted'];
        }

        return ['success' => false, 'message' => 'Delete failed'];
    }

    /**
     * Get landlord properties
     */
    public function getLandlordProperties($landlordId, $limit = 10, $offset = 0)
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM properties 
            WHERE landlord_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("iii", $landlordId, $limit, $offset);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($results as &$property) {
            $property['amenities'] = json_decode($property['amenities'] ?? '[]', true);
            $property['rules'] = json_decode($property['rules'] ?? '[]', true);
            $property['image_urls'] = json_decode($property['image_urls'] ?? '[]', true);
        }

        return $results;
    }
}

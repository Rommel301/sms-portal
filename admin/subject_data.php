
<?php
/*
|--------------------------------------------------------------------------
| SUBJECT DATA LIST - IMPROVED WITH CATEGORIES
|--------------------------------------------------------------------------
| This file ONLY contains the subject list data.
| Functions are in enrollment_data.php
|--------------------------------------------------------------------------
*/

$subject_list = [
    // === 1st Year Subjects ===
    '101' => [
        'name'       => 'Introduction to Computing',
        'units'      => 3.0,
        'instructor' => 'Prof. Juan Dela Cruz',
        'schedule'   => 'MWF 9:00-10:00',
        'category'   => '1st Year'
    ],
    '102' => [
        'name'       => 'Programming Logic and Design',
        'units'      => 3.0,
        'instructor' => 'Prof. Maria Santos',
        'schedule'   => 'TTH 10:00-11:30',
        'category'   => '1st Year'
    ],
    '103' => [
        'name'       => 'Data Structures and Algorithms',
        'units'      => 3.0,
        'instructor' => 'Prof. Jose Rizal',
        'schedule'   => 'MWF 1:00-2:00',
        'category'   => '1st Year'
    ],
    '104' => [
        'name'       => 'Database Management System',
        'units'      => 3.0,
        'instructor' => 'Prof. Andres Bonifacio',
        'schedule'   => 'TTH 2:00-3:30',
        'category'   => '1st Year'
    ],
    '105' => [
        'name'       => 'Web Development',
        'units'      => 3.0,
        'instructor' => 'Prof. Emilio Aguinaldo',
        'schedule'   => 'MWF 3:00-4:00',
        'category'   => '1st Year'
    ],

    // === 2nd Year Subjects ===
    '201' => [
        'name'       => 'Advanced Programming',
        'units'      => 3.0,
        'instructor' => 'Prof. Jose Rizal',
        'schedule'   => 'MWF 11:00-12:00',
        'category'   => '2nd Year'
    ],
    '202' => [
        'name'       => 'Software Engineering',
        'units'      => 3.0,
        'instructor' => 'Prof. Apolinario Mabini',
        'schedule'   => 'TTH 4:00-5:30',
        'category'   => '2nd Year'
    ],
    '203' => [
        'name'       => 'Computer Networks',
        'units'      => 3.0,
        'instructor' => 'Prof. Gregorio Del Pilar',
        'schedule'   => 'MWF 10:00-11:00',
        'category'   => '2nd Year'
    ],
    '204' => [
        'name'       => 'Operating Systems',
        'units'      => 3.0,
        'instructor' => 'Prof. Antonio Luna',
        'schedule'   => 'TTH 1:00-2:30',
        'category'   => '2nd Year'
    ],
    '205' => [
        'name'       => 'Information Assurance and Security',
        'units'      => 3.0,
        'instructor' => 'Prof. Marcelo Del Pilar',
        'schedule'   => 'MWF 2:00-3:00',
        'category'   => '2nd Year'
    ],

    // === 3rd Year Subjects ===
    '301' => [
        'name'       => 'Capstone Project 1',
        'units'      => 3.0,
        'instructor' => 'Prof. Jose Rizal',
        'schedule'   => 'TTH 9:00-10:30',
        'category'   => '3rd Year'
    ],
    '302' => [
        'name'       => 'Mobile Application Development',
        'units'      => 3.0,
        'instructor' => 'Prof. Andres Bonifacio',
        'schedule'   => 'MWF 4:00-5:00',
        'category'   => '3rd Year'
    ],
    '303' => [
        'name'       => 'Cloud Computing',
        'units'      => 3.0,
        'instructor' => 'Prof. Emilio Aguinaldo',
        'schedule'   => 'TTH 10:30-12:00',
        'category'   => '3rd Year'
    ],
    '304' => [
        'name'       => 'Artificial Intelligence',
        'units'      => 3.0,
        'instructor' => 'Prof. Maria Santos',
        'schedule'   => 'MWF 8:00-9:00',
        'category'   => '3rd Year'
    ],

    // === 4th Year Subjects ===
    '401' => [
        'name'       => 'Capstone Project 2',
        'units'      => 3.0,
        'instructor' => 'Prof. Jose Rizal',
        'schedule'   => 'TTH 1:00-2:30',
        'category'   => '4th Year'
    ],
    '402' => [
        'name'       => 'IT Governance and Management',
        'units'      => 3.0,
        'instructor' => 'Prof. Juan Dela Cruz',
        'schedule'   => 'MWF 2:00-3:00',
        'category'   => '4th Year'
    ],
    '403' => [
        'name'       => 'Systems Analysis and Design',
        'units'      => 3.0,
        'instructor' => 'Prof. Apolinario Mabini',
        'schedule'   => 'TTH 3:30-5:00',
        'category'   => '4th Year'
    ],
    '404' => [
        'name'       => 'Research Methods',
        'units'      => 3.0,
        'instructor' => 'Prof. Gregorio Del Pilar',
        'schedule'   => 'MWF 1:00-2:00',
        'category'   => '4th Year'
    ],

    // === General Education Subjects ===
    'G101' => [
        'name'       => 'Purposive Communication',
        'units'      => 3.0,
        'instructor' => 'Prof. Maria Santos',
        'schedule'   => 'TTH 8:00-9:30',
        'category'   => 'General Education'
    ],
    'G102' => [
        'name'       => 'Mathematics in the Modern World',
        'units'      => 3.0,
        'instructor' => 'Prof. Jose Rizal',
        'schedule'   => 'MWF 10:00-11:00',
        'category'   => 'General Education'
    ],
    'G103' => [
        'name'       => 'Science, Technology, and Society',
        'units'      => 3.0,
        'instructor' => 'Prof. Andres Bonifacio',
        'schedule'   => 'TTH 2:00-3:30',
        'category'   => 'General Education'
    ],
    'G104' => [
        'name'       => 'Art Appreciation',
        'units'      => 3.0,
        'instructor' => 'Prof. Juan Dela Cruz',
        'schedule'   => 'MWF 11:00-12:00',
        'category'   => 'General Education'
    ],
];

// Helper function to get subject details by code
function get_subject_details($code) {
    global $subject_list;
    return $subject_list[$code] ?? null;
}

// Helper function to get all subject codes
function get_all_subject_codes() {
    global $subject_list;
    return array_keys($subject_list);
}

// Helper function to get subjects by category
function get_subjects_by_category($category) {
    global $subject_list;
    $result = [];
    foreach ($subject_list as $code => $data) {
        if (($data['category'] ?? '') === $category) {
            $result[$code] = $data;
        }
    }
    return $result;
}
?>

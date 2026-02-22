<?php
/**
 * Test Mock Responses
 * Provides realistic mock responses for testing AI document features
 */

// Set content type to JSON
header('Content-Type: application/json');

// Get the requested mock response type
$type = isset($_GET['type']) ? $_GET['type'] : 'summary';

// Sample document types to make the responses more realistic
$documentTypes = [
    'Contract Agreement',
    'Financial Report',
    'Policy Document',
    'Meeting Minutes',
    'Project Proposal'
];

// Randomly select a document type for this response
$selectedType = $documentTypes[array_rand($documentTypes)];

// Create different mock responses based on the document type
switch ($selectedType) {
    case 'Contract Agreement':
        $mockResponses = [
            'summary' => [
                'success' => true,
                'summary' => 'This contract agreement, dated June 15, 2023, is between Southern Cross College (SCC) and Acme Technology Solutions for the implementation of a new document management system. The agreement outlines a 12-month project timeline with a total budget of $125,000, payable in quarterly installments. Key deliverables include software installation, data migration, staff training, and 24-month technical support. The contract includes provisions for confidentiality, intellectual property rights, termination conditions, and dispute resolution procedures. Both parties have agreed to performance metrics and regular progress reviews throughout the implementation phase.',
                'keyPoints' => [
                    'Agreement between Southern Cross College and Acme Technology Solutions dated June 15, 2023',
                    'Implementation of document management system with 12-month timeline',
                    'Total budget of $125,000 with quarterly payment schedule',
                    'Deliverables include software, migration, training, and 24-month support',
                    'Contains confidentiality, IP rights, and termination clauses',
                    'Performance metrics and progress reviews are required'
                ]
            ],
            'analysis' => [
                'success' => true,
                'classification' => [
                    ['name' => 'Contract', 'confidence' => 95],
                    ['name' => 'Legal Document', 'confidence' => 92],
                    ['name' => 'Service Agreement', 'confidence' => 87],
                    ['name' => 'Technology Implementation', 'confidence' => 82]
                ],
                'entities' => [
                    ['text' => 'Southern Cross College', 'type' => 'ORGANIZATION', 'relevance' => 98],
                    ['text' => 'Acme Technology Solutions', 'type' => 'ORGANIZATION', 'relevance' => 96],
                    ['text' => 'June 15, 2023', 'type' => 'DATE', 'relevance' => 94],
                    ['text' => '$125,000', 'type' => 'MONEY', 'relevance' => 92],
                    ['text' => '12-month', 'type' => 'DURATION', 'relevance' => 88],
                    ['text' => '24-month technical support', 'type' => 'SERVICE', 'relevance' => 86],
                    ['text' => 'document management system', 'type' => 'PRODUCT', 'relevance' => 90]
                ],
                'sentiment' => [
                    'overall' => 0.1,
                    'sentiment_label' => 'Neutral',
                    'tones' => [
                        ['tone' => 'Formal', 'intensity' => 0.95],
                        ['tone' => 'Professional', 'intensity' => 0.92],
                        ['tone' => 'Precise', 'intensity' => 0.88],
                        ['tone' => 'Authoritative', 'intensity' => 0.75]
                    ]
                ],
                'keywords' => [
                    'contract', 'agreement', 'Southern Cross College', 'Acme Technology', 
                    'document management', 'implementation', 'quarterly installments',
                    'confidentiality', 'intellectual property', 'termination', 'dispute resolution'
                ],
                'summary' => 'This contract agreement establishes a partnership between Southern Cross College and Acme Technology Solutions for implementing a document management system over 12 months for $125,000.',
                'keyPoints' => [
                    'Legally binding agreement between two organizations',
                    'Detailed payment structure with quarterly installments',
                    'Comprehensive deliverables with specific timelines',
                    'Strong legal protections for both parties',
                    'Clear performance metrics and evaluation criteria'
                ]
            ]
        ];
        break;
        
    case 'Financial Report':
        $mockResponses = [
            'summary' => [
                'success' => true,
                'summary' => 'This quarterly financial report for Q2 2023 (April-June) shows Southern Cross College\'s financial performance with total revenue of $3.2 million, a 7% increase from the previous quarter. Operating expenses were $2.8 million, resulting in a net income of $400,000. The report highlights strong performance in tuition revenue (up 9%) and research grants (up 12%), while noting increased costs in facility maintenance (up 15%) and IT infrastructure (up 18%). Cash reserves stand at $5.1 million, with $1.2 million allocated for the upcoming campus expansion project. The debt-to-equity ratio improved from 0.35 to 0.32, and all loan covenants are being met. The report projects a 5% revenue growth for Q3 2023 based on increased enrollment and new partnership agreements.',
                'keyPoints' => [
                    'Q2 2023 financial report shows $3.2 million revenue (7% increase)',
                    'Net income of $400,000 with $2.8 million in operating expenses',
                    'Strong growth in tuition revenue (9%) and research grants (12%)',
                    'Increased costs in facility maintenance (15%) and IT infrastructure (18%)',
                    'Cash reserves of $5.1 million with $1.2 million allocated for campus expansion',
                    'Improved debt-to-equity ratio from 0.35 to 0.32',
                    'Projected 5% revenue growth for Q3 2023'
                ]
            ],
            'analysis' => [
                'success' => true,
                'classification' => [
                    ['name' => 'Financial Report', 'confidence' => 98],
                    ['name' => 'Quarterly Statement', 'confidence' => 94],
                    ['name' => 'Educational Institution Finance', 'confidence' => 89],
                    ['name' => 'Internal Document', 'confidence' => 85]
                ],
                'entities' => [
                    ['text' => 'Southern Cross College', 'type' => 'ORGANIZATION', 'relevance' => 97],
                    ['text' => 'Q2 2023', 'type' => 'DATE', 'relevance' => 96],
                    ['text' => '$3.2 million', 'type' => 'MONEY', 'relevance' => 95],
                    ['text' => '$2.8 million', 'type' => 'MONEY', 'relevance' => 94],
                    ['text' => '$400,000', 'type' => 'MONEY', 'relevance' => 93],
                    ['text' => '$5.1 million', 'type' => 'MONEY', 'relevance' => 92],
                    ['text' => '$1.2 million', 'type' => 'MONEY', 'relevance' => 91],
                    ['text' => 'campus expansion project', 'type' => 'PROJECT', 'relevance' => 88]
                ],
                'sentiment' => [
                    'overall' => 0.45,
                    'sentiment_label' => 'Moderately Positive',
                    'tones' => [
                        ['tone' => 'Analytical', 'intensity' => 0.90],
                        ['tone' => 'Factual', 'intensity' => 0.95],
                        ['tone' => 'Optimistic', 'intensity' => 0.65],
                        ['tone' => 'Professional', 'intensity' => 0.88]
                    ]
                ],
                'keywords' => [
                    'quarterly financial report', 'revenue', 'operating expenses', 'net income', 
                    'tuition revenue', 'research grants', 'facility maintenance', 
                    'cash reserves', 'debt-to-equity ratio', 'loan covenants', 'enrollment'
                ],
                'summary' => 'Q2 2023 financial report shows strong performance with $3.2M revenue (7% increase), $400K net income, and improved financial ratios, with positive projections for Q3.',
                'keyPoints' => [
                    'Overall positive financial performance for the quarter',
                    'Revenue growth in key educational income streams',
                    'Strategic investments in facilities and IT infrastructure',
                    'Strong cash position with allocated funds for expansion',
                    'Improved financial stability with better debt ratios'
                ]
            ]
        ];
        break;
        
    case 'Policy Document':
        $mockResponses = [
            'summary' => [
                'success' => true,
                'summary' => 'This document outlines Southern Cross College\'s updated Data Protection and Privacy Policy, effective September 1, 2023. The policy establishes guidelines for collecting, storing, processing, and sharing personal data in compliance with relevant privacy laws. It defines the types of data collected (student records, employee information, financial data), the purposes for collection, and retention periods. The policy mandates security measures including encryption, access controls, and regular audits. It details data subject rights (access, rectification, erasure) and procedures for exercising these rights. The document assigns responsibilities to key roles including the Data Protection Officer and department heads, and outlines breach notification procedures. Training requirements and compliance monitoring processes are specified, with provisions for policy review every 12 months.',
                'keyPoints' => [
                    'Updated Data Protection and Privacy Policy effective September 1, 2023',
                    'Comprehensive guidelines for handling personal data in compliance with privacy laws',
                    'Defines data types, collection purposes, and retention periods',
                    'Mandates security measures including encryption and access controls',
                    'Details data subject rights and procedures for exercising them',
                    'Assigns responsibilities to Data Protection Officer and department heads',
                    'Includes breach notification procedures and training requirements',
                    'Policy to be reviewed every 12 months'
                ]
            ],
            'analysis' => [
                'success' => true,
                'classification' => [
                    ['name' => 'Policy Document', 'confidence' => 97],
                    ['name' => 'Data Protection', 'confidence' => 95],
                    ['name' => 'Privacy Policy', 'confidence' => 94],
                    ['name' => 'Compliance Document', 'confidence' => 90]
                ],
                'entities' => [
                    ['text' => 'Southern Cross College', 'type' => 'ORGANIZATION', 'relevance' => 96],
                    ['text' => 'Data Protection and Privacy Policy', 'type' => 'DOCUMENT', 'relevance' => 98],
                    ['text' => 'September 1, 2023', 'type' => 'DATE', 'relevance' => 95],
                    ['text' => 'Data Protection Officer', 'type' => 'ROLE', 'relevance' => 92],
                    ['text' => 'student records', 'type' => 'DATA_TYPE', 'relevance' => 88],
                    ['text' => 'employee information', 'type' => 'DATA_TYPE', 'relevance' => 87],
                    ['text' => 'financial data', 'type' => 'DATA_TYPE', 'relevance' => 86]
                ],
                'sentiment' => [
                    'overall' => 0.05,
                    'sentiment_label' => 'Neutral',
                    'tones' => [
                        ['tone' => 'Formal', 'intensity' => 0.92],
                        ['tone' => 'Authoritative', 'intensity' => 0.85],
                        ['tone' => 'Instructive', 'intensity' => 0.88],
                        ['tone' => 'Precise', 'intensity' => 0.90]
                    ]
                ],
                'keywords' => [
                    'data protection', 'privacy policy', 'personal data', 'compliance', 
                    'encryption', 'access controls', 'data subject rights', 
                    'breach notification', 'retention periods', 'Data Protection Officer'
                ],
                'summary' => 'Southern Cross College\'s Data Protection and Privacy Policy establishes comprehensive guidelines for handling personal data in compliance with privacy laws.',
                'keyPoints' => [
                    'Formal institutional policy with regulatory compliance focus',
                    'Clear definitions of data types and handling procedures',
                    'Strong emphasis on security measures and controls',
                    'Detailed rights for data subjects with exercise procedures',
                    'Structured roles and responsibilities for implementation'
                ]
            ]
        ];
        break;
        
    case 'Meeting Minutes':
        $mockResponses = [
            'summary' => [
                'success' => true,
                'summary' => 'These are minutes from the Southern Cross College Academic Council meeting held on July 12, 2023, from 2:00-4:30 PM in the Administration Building, Room 302. Attendees included 15 council members, with Dr. James Wilson presiding. The meeting addressed five agenda items: approval of previous minutes, curriculum changes for the upcoming semester, faculty hiring updates, research grant allocations, and the new student mentoring program. Key decisions included approval of three new courses in the Computer Science department, authorization to proceed with hiring two associate professors for the Business School, allocation of $350,000 for research grants across departments, and approval of the expanded student mentoring program. The council also formed a subcommittee to review the proposed changes to the academic integrity policy, with recommendations due by the next meeting on August 16, 2023.',
                'keyPoints' => [
                    'Academic Council meeting on July 12, 2023 (2:00-4:30 PM) in Admin Building Room 302',
                    'Attended by 15 council members with Dr. James Wilson presiding',
                    'Approved three new Computer Science courses for upcoming semester',
                    'Authorized hiring two associate professors for Business School',
                    'Allocated $350,000 for departmental research grants',
                    'Approved expanded student mentoring program',
                    'Formed subcommittee to review academic integrity policy changes',
                    'Next meeting scheduled for August 16, 2023'
                ]
            ],
            'analysis' => [
                'success' => true,
                'classification' => [
                    ['name' => 'Meeting Minutes', 'confidence' => 98],
                    ['name' => 'Academic Document', 'confidence' => 92],
                    ['name' => 'Administrative Record', 'confidence' => 90],
                    ['name' => 'Internal Communication', 'confidence' => 88]
                ],
                'entities' => [
                    ['text' => 'Southern Cross College', 'type' => 'ORGANIZATION', 'relevance' => 96],
                    ['text' => 'Academic Council', 'type' => 'GROUP', 'relevance' => 95],
                    ['text' => 'July 12, 2023', 'type' => 'DATE', 'relevance' => 94],
                    ['text' => 'August 16, 2023', 'type' => 'DATE', 'relevance' => 92],
                    ['text' => 'Dr. James Wilson', 'type' => 'PERSON', 'relevance' => 93],
                    ['text' => 'Administration Building, Room 302', 'type' => 'LOCATION', 'relevance' => 88],
                    ['text' => 'Computer Science department', 'type' => 'ORGANIZATION', 'relevance' => 89],
                    ['text' => 'Business School', 'type' => 'ORGANIZATION', 'relevance' => 88],
                    ['text' => '$350,000', 'type' => 'MONEY', 'relevance' => 90]
                ],
                'sentiment' => [
                    'overall' => 0.25,
                    'sentiment_label' => 'Slightly Positive',
                    'tones' => [
                        ['tone' => 'Formal', 'intensity' => 0.85],
                        ['tone' => 'Factual', 'intensity' => 0.95],
                        ['tone' => 'Neutral', 'intensity' => 0.80],
                        ['tone' => 'Informative', 'intensity' => 0.90]
                    ]
                ],
                'keywords' => [
                    'Academic Council', 'meeting minutes', 'curriculum changes', 
                    'faculty hiring', 'research grants', 'student mentoring program', 
                    'academic integrity policy', 'subcommittee', 'Computer Science'
                ],
                'summary' => 'Minutes from the July 12 Academic Council meeting document decisions on curriculum changes, faculty hiring, research grants, and student mentoring initiatives.',
                'keyPoints' => [
                    'Formal record of institutional decision-making process',
                    'Multiple significant academic decisions approved',
                    'Financial resource allocation for research activities',
                    'Focus on both faculty development and student support',
                    'Ongoing policy review through committee structure'
                ]
            ]
        ];
        break;
        
    case 'Project Proposal':
        $mockResponses = [
            'summary' => [
                'success' => true,
                'summary' => 'This proposal outlines the "Digital Campus Initiative" project at Southern Cross College, aimed at modernizing campus technology infrastructure and digital services over 18 months with a budget of $1.8 million. The project has four main objectives: upgrading network infrastructure, implementing a unified communication system, creating a mobile campus app, and establishing a digital learning commons. Key benefits include improved connectivity (95% campus coverage), enhanced learning experiences, streamlined administrative processes, and increased operational efficiency. The implementation plan includes four phases: assessment and planning (2 months), infrastructure development (6 months), system integration (6 months), and deployment and training (4 months). The budget allocates funds to hardware ($750K), software ($450K), professional services ($350K), training ($150K), and contingency ($100K). Success metrics include network performance, system adoption rates, user satisfaction, and operational efficiency improvements.',
                'keyPoints' => [
                    '"Digital Campus Initiative" project with $1.8 million budget over 18 months',
                    'Four objectives: network upgrade, unified communication, mobile app, digital learning commons',
                    'Benefits include improved connectivity, enhanced learning, streamlined processes',
                    'Four-phase implementation: assessment, infrastructure, integration, deployment',
                    'Budget allocations: hardware ($750K), software ($450K), services ($350K), training ($150K)',
                    'Success metrics include performance, adoption rates, satisfaction, efficiency improvements'
                ]
            ],
            'analysis' => [
                'success' => true,
                'classification' => [
                    ['name' => 'Project Proposal', 'confidence' => 96],
                    ['name' => 'Technology Initiative', 'confidence' => 94],
                    ['name' => 'Strategic Planning Document', 'confidence' => 90],
                    ['name' => 'Budget Request', 'confidence' => 88]
                ],
                'entities' => [
                    ['text' => 'Southern Cross College', 'type' => 'ORGANIZATION', 'relevance' => 96],
                    ['text' => 'Digital Campus Initiative', 'type' => 'PROJECT', 'relevance' => 98],
                    ['text' => '18 months', 'type' => 'DURATION', 'relevance' => 92],
                    ['text' => '$1.8 million', 'type' => 'MONEY', 'relevance' => 95],
                    ['text' => '$750K', 'type' => 'MONEY', 'relevance' => 90],
                    ['text' => '$450K', 'type' => 'MONEY', 'relevance' => 89],
                    ['text' => '$350K', 'type' => 'MONEY', 'relevance' => 88],
                    ['text' => '$150K', 'type' => 'MONEY', 'relevance' => 87],
                    ['text' => '$100K', 'type' => 'MONEY', 'relevance' => 86],
                    ['text' => 'digital learning commons', 'type' => 'FACILITY', 'relevance' => 91]
                ],
                'sentiment' => [
                    'overall' => 0.65,
                    'sentiment_label' => 'Positive',
                    'tones' => [
                        ['tone' => 'Optimistic', 'intensity' => 0.85],
                        ['tone' => 'Persuasive', 'intensity' => 0.82],
                        ['tone' => 'Professional', 'intensity' => 0.90],
                        ['tone' => 'Forward-looking', 'intensity' => 0.88]
                    ]
                ],
                'keywords' => [
                    'Digital Campus Initiative', 'technology infrastructure', 'network upgrade', 
                    'unified communication', 'mobile campus app', 'digital learning commons', 
                    'implementation plan', 'budget allocation', 'success metrics'
                ],
                'summary' => 'The Digital Campus Initiative proposal outlines an 18-month, $1.8M project to modernize Southern Cross College\'s technology infrastructure and digital services.',
                'keyPoints' => [
                    'Comprehensive technology modernization initiative',
                    'Significant investment with detailed budget allocation',
                    'Phased implementation approach with clear timeline',
                    'Multiple integrated components addressing different needs',
                    'Strong focus on measurable outcomes and benefits'
                ]
            ]
        ];
        break;
        
    default:
        $mockResponses = [
            'summary' => [
                'success' => true,
                'summary' => 'This document appears to be a standard administrative communication from Southern Cross College regarding institutional procedures and policies. It contains information about operational guidelines, departmental responsibilities, and upcoming changes to administrative processes. The document references several key dates for implementation and includes contact information for relevant department heads. While primarily informational in nature, it requires action from certain stakeholders by specified deadlines.',
                'keyPoints' => [
                    'Administrative communication from Southern Cross College',
                    'Outlines operational guidelines and departmental responsibilities',
                    'Describes upcoming changes to administrative processes',
                    'Contains key implementation dates and deadlines',
                    'Includes contact information for relevant department heads',
                    'Requires action from specific stakeholders'
                ]
            ],
            'analysis' => [
                'success' => true,
                'classification' => [
                    ['name' => 'Administrative Document', 'confidence' => 92],
                    ['name' => 'Internal Communication', 'confidence' => 88],
                    ['name' => 'Procedural Guidelines', 'confidence' => 85],
                    ['name' => 'Institutional Policy', 'confidence' => 80]
                ],
                'entities' => [
                    ['text' => 'Southern Cross College', 'type' => 'ORGANIZATION', 'relevance' => 95],
                    ['text' => 'Administrative Services Department', 'type' => 'ORGANIZATION', 'relevance' => 90],
                    ['text' => 'October 15, 2023', 'type' => 'DATE', 'relevance' => 88],
                    ['text' => 'November 30, 2023', 'type' => 'DATE', 'relevance' => 86],
                    ['text' => 'Dr. Robert Chen', 'type' => 'PERSON', 'relevance' => 85],
                    ['text' => 'Administrative Procedures Committee', 'type' => 'ORGANIZATION', 'relevance' => 84]
                ],
                'sentiment' => [
                    'overall' => 0.1,
                    'sentiment_label' => 'Neutral',
                    'tones' => [
                        ['tone' => 'Formal', 'intensity' => 0.90],
                        ['tone' => 'Informative', 'intensity' => 0.88],
                        ['tone' => 'Instructive', 'intensity' => 0.85],
                        ['tone' => 'Professional', 'intensity' => 0.92]
                    ]
                ],
                'keywords' => [
                    'administrative procedures', 'institutional policy', 'departmental responsibilities', 
                    'implementation timeline', 'operational guidelines', 'stakeholders', 
                    'compliance requirements', 'process changes'
                ],
                'summary' => 'Standard administrative communication outlining procedural changes, departmental responsibilities, and implementation timelines for Southern Cross College.',
                'keyPoints' => [
                    'Formal institutional communication with procedural focus',
                    'Contains specific timelines and deadlines for implementation',
                    'Assigns clear responsibilities to departments and individuals',
                    'Requires specific actions from identified stakeholders',
                    'Follows standard administrative document structure'
                ]
            ]
        ];
}

// Return the requested response type
if ($type === 'analysis') {
    echo json_encode($mockResponses['analysis']);
} else {
    echo json_encode($mockResponses['summary']);
}
?> 
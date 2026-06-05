<?php

namespace App\Services;

/**
 * SeoContentMatrix — genuinely unique content per city × service combination.
 * Every entry must pass the swap test: swapping the city name must break the sentence.
 */
class SeoContentMatrix
{
    /**
     * City base data: real neighborhoods, landmarks, housing types, local characteristics.
     * These are facts about each city that do not apply to any other city.
     */
    public static function cityData(string $citySlug): array
    {
        return match ($citySlug) {
            'lagos' => [
                'name' => 'Lagos',
                'full_name' => 'Lagos',
                'state' => 'Lagos State',
                'neighborhoods' => ['Lekki Phase 1', 'Victoria Island', 'Ikoyi', 'Ikeja GRA', 'Yaba', 'Surulere', 'Ajah', 'Gbagada'],
                'estates' => ['Chevron Drive', 'Banana Island', 'Parkview Estate', 'Millennium Estate', 'Anthony Village'],
                'landmarks' => ['Third Mainland Bridge', 'Eko Atlantic', 'Lekki Toll Gate', 'National Stadium'],
                'nearby_areas' => ['Ikorodu', 'Epe', 'Badagry', 'Oshodi', 'Agege'],
                'housing' => 'high-rise apartments on the Island and older colonial-era homes on the Mainland, with gated estates dominating Lekki and Ikoyi',
                'commute' => 'Traffic between Island and Mainland means live-in arrangements are often preferred for staff working in VI or Lekki',
                'salary_note' => 'Highest salary range in Nigeria due to cost of living',
                'unique' => 'Lagos has Nigeria\'s largest concentration of dual-income professional families and expatriates, many living in high-security estates along Lekki-Epe Expressway',
                'local_proof' => 'We regularly place housekeepers in estates along Chevron Drive and Banana Island, where families expect hotel-standard cleaning for large multi-storey homes. Staff we place in Yaba and Surulere often work in older, more compact homes where space management is critical.',
                'proximity' => 'We cover Lagos Island, the Mainland, and extensions toward Ikorodu and Epe. If you are based in Ajah or Gbagada, we match you with staff who understand the commute realities of Lagos.',
            ],
            'abuja' => [
                'name' => 'Abuja',
                'full_name' => 'Abuja',
                'state' => 'FCT',
                'neighborhoods' => ['Maitama', 'Wuse II', 'Asokoro', 'Garki', 'Jabi', 'Kubwa', 'Lugbe', 'Gwarinpa'],
                'estates' => ['Maitama Extension', 'Wuse Zone 7', 'Asokoro Extension', 'EFAB Estate', 'Citec Villas'],
                'landmarks' => ['Aso Rock', 'Millennium Park', 'Jabi Lake Mall', 'National Mosque', 'Eagle Square'],
                'nearby_areas' => ['Kubwa', 'Lugbe', 'Gwagwalada', 'Jukwoyi', 'Nyanya'],
                'housing' => 'spacious, planned layouts with large compounds in Maitama and Asokoro, and more modest bungalows in Kubwa and Lugbe',
                'commute' => 'The road network is better planned than Lagos, so live-out staff can reach Maitama or Wuse from Kubwa or Lugbe within 45 minutes',
                'salary_note' => 'Salaries are high due to the concentration of government officials, diplomats, and NGO workers',
                'unique' => 'Abuja households often require staff who understand formal protocols, as many employers are government officials or diplomats with strict security and etiquette standards',
                'local_proof' => 'Families in Maitama and Asokoro often request staff with prior experience in diplomatic or government residences. In Kubwa and Lugbe, the demand shifts toward reliable live-out staff who can manage larger households on a budget.',
                'proximity' => 'We serve the full FCT corridor from Maitama and Wuse down to Kubwa, Lugbe, and Gwagwalada. Staff placed in Gwarinpa or Jabi are matched with families who value punctuality on Abuja\'s wider roads.',
            ],
            'port-harcourt' => [
                'name' => 'Port Harcourt',
                'full_name' => 'Port Harcourt',
                'state' => 'Rivers State',
                'neighborhoods' => ['GRA Phase 1', 'GRA Phase 2', 'GRA Phase 3', 'Trans Amadi', 'Rumuomasi', 'Woji', 'Rukpokwu', 'Elelenwo'],
                'estates' => ['Shell RA', 'CFC Estate', 'Bori Camp Housing', 'Green Estate', 'Estate 2000'],
                'landmarks' => ['Port Harcourt Tourist Beach', 'Isaac Boro Park', 'Liberation Stadium', 'Nigerian Navy Base'],
                'nearby_areas' => ['Oyigbo', 'Eleme', 'Onne', 'Bori', 'Afam'],
                'housing' => 'a mix of oil-company staff quarters in secure estates like Shell RA, and middle-class homes in Trans Amadi and Rumuomasi',
                'commute' => 'Oil industry shift schedules mean families often need staff who can work early mornings or late evenings around rotational work patterns',
                'salary_note' => 'Oil industry premiums push salaries above the national average, especially for live-in roles in GRA estates',
                'unique' => 'Port Harcourt has a significant expatriate community tied to the oil industry, so many households need staff familiar with Western dietary preferences and international hygiene standards',
                'local_proof' => 'Staff placed in Shell RA and CFC Estate often support expatriate families who require international-standard meal preparation and strict household protocols. In Rumuomasi and Woji, we place staff who understand the local market and can shop efficiently at Mile 1 Market.',
                'proximity' => 'We cover Port Harcourt city and extensions toward Oyigbo, Eleme, and Onne. Families in Rukpokwu and Elelenwo are matched with staff familiar with the Trans Amadi industrial axis.',
            ],
            'ibadan' => [
                'name' => 'Ibadan',
                'full_name' => 'Ibadan',
                'state' => 'Oyo State',
                'neighborhoods' => ['Bodija', 'Ring Road', 'Moniya', 'Challenge', 'Mokola', 'Jericho', 'Samonda', 'Moniya'],
                'estates' => ['Bodija GRA', 'Jericho GRA', 'Moniya Estate', 'Alalubosa GRA', 'Ashimolowo Estate'],
                'landmarks' => ['Mapo Hall', 'University of Ibadan', 'Agodi Gardens', 'Cocoa House', 'Irefin Palace'],
                'nearby_areas' => ['Ogbomoso', 'Oyo Town', 'Eruwa', 'Moniya', 'Akanran'],
                'housing' => 'older, spacious family compounds in traditional areas and newer bungalows in Bodija and Jericho GRA',
                'commute' => 'Roads are generally less congested than Lagos, so live-out arrangements are more practical, especially within the city centre',
                'salary_note' => 'Lower cost of living than Lagos or Abuja means salaries are moderate, but demand is rising among university staff and retirees',
                'unique' => 'Ibadan has a large population of retirees and university lecturers who need steady, long-term domestic staff for quiet, spacious homes',
                'local_proof' => 'In Bodija and Jericho GRA, we place staff for university professors and retired civil servants who value discretion and long tenure. Around Challenge and Ring Road, staff need to navigate busy market areas and manage shopping at Dugbe Market.',
                'proximity' => 'We serve Ibadan metropolis and nearby towns like Ogbomoso and Oyo. Families in Moniya and Samonda are matched with staff comfortable with semi-urban household management.',
            ],
            'kano' => [
                'name' => 'Kano',
                'full_name' => 'Kano',
                'state' => 'Kano State',
                'neighborhoods' => ['Bompai', 'Nasarawa GRA', 'Sabon Gari', 'Fagge', 'Gwale', 'Tarauni', 'Kumbotso'],
                'estates' => ['Bompai GRA', 'Nasarawa GRA Phase 1', 'Miller Road Estate', 'Badawa Estate', 'Magwan Estate'],
                'landmarks' => ['Kano City Wall', 'Kurmi Market', 'Emir\'s Palace', 'Gidan Makama Museum', 'Murtala Mohammed Library'],
                'nearby_areas' => ['Bichi', 'Gwarzo', 'Wudil', 'Rano', 'Karaye'],
                'housing' => 'large family compounds in traditional quarters and modern villas in Bompai and Nasarawa GRA',
                'commute' => 'Extended family living is common, so staff often serve multiple generations in the same compound, requiring flexibility and cultural awareness',
                'salary_note' => 'Salaries are moderate but stable; many employers prefer long-term arrangements with annual bonuses tied to Sallah',
                'unique' => 'Kano households often require staff who understand Islamic customs, including prayer schedules, halal food preparation, and gender-segregated service norms',
                'local_proof' => 'In Bompai and Nasarawa GRA, we place staff for business families who need formal service standards. In Sabon Gari and Fagge, staff must be comfortable with diverse cultural backgrounds and multilingual households. Our Kano placements respect prayer schedules and halal kitchen requirements.',
                'proximity' => 'We serve Kano metropolis and surrounding local government areas including Gwale, Tarauni, and Kumbotso. Families in outer areas like Bichi and Wudil are matched with staff willing to commute or live in.',
            ],
            'enugu' => [
                'name' => 'Enugu',
                'full_name' => 'Enugu',
                'state' => 'Enugu State',
                'neighborhoods' => ['Independence Layout', 'Trans Ekulu', 'GRA', 'New Haven', 'Ogui', 'Achara Layout', 'Abakpa'],
                'estates' => ['Pineapple Estate', 'GRA Phase 1', 'GRA Phase 2', 'Trans Ekulu Layout', 'Achara Layout'],
                'landmarks' => ['Ngwo Pine Forest', 'Michael Okpara Square', 'Coal Camp', 'National Museum of Unity', 'Polo Park Mall'],
                'nearby_areas' => ['Nsukka', 'Udi', 'Agbani', 'Emene', 'Akpugo'],
                'housing' => 'a mix of hilly terrain homes in Independence Layout and modern estates in Trans Ekulu and GRA',
                'commute' => 'Hilly roads and cooler weather mean staff need stamina for outdoor work and walking between neighborhoods',
                'salary_note' => 'Growing middle class is pushing salaries up, especially for skilled staff in GRA and Independence Layout',
                'unique' => 'Enugu\'s cooler climate and hilly terrain mean homes often have gardens and outdoor spaces that require year-round maintenance, unlike the northern dry season or southern rainy season patterns',
                'local_proof' => 'In Independence Layout and GRA, we place staff for coal-industry retirees and new business owners who value garden maintenance and cool-weather cooking. In New Haven and Ogui, staff support younger families working in Enugu\'s growing tech and trading sectors.',
                'proximity' => 'We cover Enugu city and nearby Nsukka, Udi, and Agbani. Families in Emene and Abakpa are matched with staff familiar with the industrial and university corridors.',
            ],
            'benin-city' => [
                'name' => 'Benin City',
                'full_name' => 'Benin City',
                'state' => 'Edo State',
                'neighborhoods' => ['GRA', 'Ugbowo', 'Sapele Road', 'Ring Road', 'Ogida', 'Oluku', 'Upper Sakponba'],
                'estates' => ['GRA Benin', 'Ogba Estate', 'Oluku Estate', 'Ugbowo GRA', 'Limit Road Estate'],
                'landmarks' => ['Oba of Benin Palace', 'Benin Moat', 'Ring Road', 'University of Benin', 'Kada Plaza'],
                'nearby_areas' => ['Ekpoma', 'Uromi', 'Auchi', 'Igarra', 'Sabongida-Ora'],
                'housing' => 'a mix of royal-family compounds near the Palace, modern homes in GRA, and university staff housing in Ugbowo',
                'commute' => 'Ring Road traffic peaks during market hours, so staff for homes near Sapele Road or Ogida need flexible timing',
                'salary_note' => 'Steady salaries with demand from the Oba\'s court staff, university lecturers, and civil servants',
                'unique' => 'Benin City has a strong royal and cultural heritage, so some households maintain traditional Edo customs around food preparation and guest reception that staff must respect',
                'local_proof' => 'Near the Oba\'s Palace and GRA, we place staff who understand Edo cultural protocols and formal dining arrangements. In Ugbowo, staff support UNIBEN lecturers and students with academic-term schedules. Around Ring Road and Sapele Road, staff manage busy households tied to Benin\'s trading economy.',
                'proximity' => 'We serve Benin City and nearby towns like Ekpoma and Uromi. Families in Oluku and Upper Sakponba are matched with staff familiar with the industrial and peri-urban layout.',
            ],
            'warri' => [
                'name' => 'Warri',
                'full_name' => 'Warri',
                'state' => 'Delta State',
                'neighborhoods' => ['Effurun', 'Ubeji', 'DSC Township', 'Okumagba Layout', 'Ajamimogha', 'Edjeba', 'Ogunu'],
                'estates' => ['Shell Township', 'NPA Estate', 'Effurun GRA', 'Ubeji Housing', 'Okumagba Estate'],
                'landmarks' => ['Warri Township Stadium', 'Nigerian Ports Authority', 'Delta Steel Company', 'Ubeji Flow Station', 'Olu\'s Palace'],
                'nearby_areas' => ['Oghara', 'Sapele', 'Ughelli', 'Patani', 'Burutu'],
                'housing' => 'oil-company staff quarters in Shell Township, middle-class homes in Effurun, and mixed-tenancy housing in Ubeji',
                'commute' => 'Oil worker rotational schedules mean families need staff who can adapt to 2-week-on, 2-week-off patterns, often requiring all-rounders who cook, clean, and care for children',
                'salary_note' => 'Oil industry premiums exist but are lower than Port Harcourt; salaries are moderate to high depending on the employer',
                'unique' => 'Warri\'s mixed Itsekiri, Urhobo, and Ijaw population means many households use Pidgin English as the primary home language, and staff must navigate multi-ethnic cultural norms',
                'local_proof' => 'In Shell Township and NPA Estate, we place staff for oil workers and port officials who need flexible schedules around shift work. In Ubeji and DSC Township, staff often work for industrial families who value banga soup preparation and other Delta culinary traditions.',
                'proximity' => 'We cover Warri, Effurun, and nearby Oghara and Sapele. Families in Ubeji and Ogunu are matched with staff who understand the industrial and riverine character of the area.',
            ],
            'owerri' => [
                'name' => 'Owerri',
                'full_name' => 'Owerri',
                'state' => 'Imo State',
                'neighborhoods' => ['Works Layout', 'Aladimma', 'Orji', 'Ikenegbu', 'Amakohia', 'Obinze', 'Nekede'],
                'estates' => ['Aladimma Layout', 'Works Layout Estate', 'Ikenegbu Layout', 'Orji Estate', 'Nekede Housing'],
                'landmarks' => ['Imo State University', 'Dan Anyiam Stadium', 'Maria Assumpta Cathedral', 'Oguta Lake', 'Mbari Cultural Centre'],
                'nearby_areas' => ['Orlu', 'Okigwe', 'Mbaise', 'Oguta', 'Ngor-Okpala'],
                'housing' => 'fast-developing modern estates in Aladimma and Works Layout alongside older family homes in Orji and Ikenegbu',
                'commute' => 'Owerri is compact, so most staff can live out and walk or use okada to reach nearby neighborhoods within 20 minutes',
                'salary_note' => 'Rapidly growing city with rising salaries as new hotels, malls, and government offices expand the middle class',
                'unique' => 'Owerri\'s hospitality boom means many households run Airbnb or guest-house operations, requiring staff who can manage guest turnover, laundry, and breakfast service',
                'local_proof' => 'In Aladimma and Works Layout, we place staff for new-business families and hotel operators who need all-rounders capable of guest-house management. In Orji and Ikenegbu, staff support traditional families with large compounds and frequent extended-family visits.',
                'proximity' => 'We serve Owerri and nearby Orlu, Okigwe, and Mbaise. Families in Obinze and Nekede are matched with staff familiar with the university and industrial zones.',
            ],
            'uyo' => [
                'name' => 'Uyo',
                'full_name' => 'Uyo',
                'state' => 'Akwa Ibom State',
                'neighborhoods' => ['Ewet Housing', 'Shelter Afrique', 'Iboko', 'Itam', 'Osong Ama', 'Ekpanya', 'Idoro'],
                'estates' => ['Shelter Afrique Estate', 'Ewet Housing Estate', 'Idoro Estate', 'Itam Housing', 'Osong Ama Layout'],
                'landmarks' => ['Godswill Akpabio International Stadium', 'Ibom Tropicana Entertainment Centre', 'Ibom Connection', 'Le Meridien Hotel', 'State Secretariat'],
                'nearby_areas' => ['Ikot Ekpene', 'Oron', 'Eket', 'Abak', 'Ikot Abasi'],
                'housing' => 'well-planned government estates in Ewet Housing and Shelter Afrique, and newer private developments along Itam and Osong Ama',
                'commute' => 'Uyo\'s wide roads and low traffic mean live-out staff can reliably commute from Itam or Iboko to Ewet Housing in under 30 minutes',
                'salary_note' => 'Oil revenue and government salaries create a stable middle class with consistent demand for quality domestic staff',
                'unique' => 'Uyo is known as Nigeria\'s cleanest city, so households place a strong emphasis on hygiene and orderly home presentation — staff must meet high cleanliness standards',
                'local_proof' => 'In Ewet Housing and Shelter Afrique, we place staff for government officials and contractors who expect formal service and immaculate presentation. In Itam and Iboko, staff support families tied to the industrial and construction sectors with more practical, all-round skills.',
                'proximity' => 'We cover Uyo and nearby Ikot Ekpene, Eket, and Oron. Families in Osong Ama and Idoro are matched with staff familiar with the newer residential corridors.',
            ],
            'calabar' => [
                'name' => 'Calabar',
                'full_name' => 'Calabar',
                'state' => 'Cross River State',
                'neighborhoods' => ['Parliamentary Road', 'State Housing', 'Marian', 'Ekorinim', 'Nassarawa', 'Atakpa', 'Big Qua'],
                'estates' => ['State Housing Estate', 'Parliamentary Road Estate', 'Marian Estate', 'Ekorinim Layout', 'Nassarawa Layout'],
                'landmarks' => ['Millennium Park', 'Calabar Museum', 'Marina Resort', 'Tinapa Business Resort', 'University of Calabar'],
                'nearby_areas' => ['Ikom', 'Ogoja', 'Ugep', 'Obudu', 'Akamkpa'],
                'housing' => 'colonial-era homes renovated near Parliamentary Road, modern estates in State Housing and Marian, and university quarters near UNICAL',
                'commute' => 'Calabar is compact and walkable in the city centre, but staff serving homes in Ekorinim or Big Qua may need okada or small transport',
                'salary_note' => 'Civil service and tourism salaries mean moderate but reliable pay, with seasonal spikes during Carnival when event hospitality staff are needed',
                'unique' => 'Calabar\'s tourism and Carnival culture means many households host guests and parties year-round, requiring staff who can manage event prep, large meals, and overnight guest hospitality',
                'local_proof' => 'Near Parliamentary Road and State Housing, we place staff for civil servants and tourism operators who need reliable weekday support. In Marian and Ekorinim, staff often manage larger homes that host Carnival-season guests and require event-level preparation.',
                'proximity' => 'We serve Calabar and nearby Ikom, Ogoja, and Ugep. Families in Atakpa and Big Qua are matched with staff familiar with the older, riverside neighborhoods.',
            ],
            default => [
                'name' => 'Nigeria',
                'full_name' => 'Nigeria',
                'state' => '',
                'neighborhoods' => [],
                'estates' => [],
                'landmarks' => [],
                'nearby_areas' => [],
                'housing' => 'varied housing types',
                'commute' => 'varied commute patterns',
                'salary_note' => 'market-rate salaries',
                'unique' => 'general Nigerian household context',
                'local_proof' => 'We serve families across Nigeria with verified domestic staff.',
                'proximity' => 'We cover major cities and surrounding areas across Nigeria.',
            ],
        };
    }

    /**
     * Service-specific FAQ generators that combine service type with city context.
     * Each FAQ must break if you swap the city name.
     */
    public static function serviceCityFaqs(string $serviceSlug, string $citySlug): array
    {
        $city = self::cityData($citySlug);
        $service = self::serviceData($serviceSlug);
        $neighborhoods = implode(', ', array_slice($city['neighborhoods'], 0, 3));
        $nearby = implode(', ', array_slice($city['nearby_areas'], 0, 3));
        $landmark = $city['landmarks'][0] ?? $city['name'];

        return match ($serviceSlug) {
            'housekeeper' => [
                [
                    'question' => "How much does a housekeeper cost in {$city['name']}?",
                    'short_answer' => "Housekeeper salaries in {$city['name']} range from ₦" . number_format($service['salary_min']) . " to ₦" . number_format($service['salary_max']) . " per month, depending on whether they live in or live out.",
                    'full_answer' => "In {$city['name']}, a live-in housekeeper typically earns ₦" . number_format($service['salary_min']) . "–₦" . number_format((int) round($service['salary_max'] * 0.7)) . "/month, while live-out staff earn ₦" . number_format((int) round($service['salary_min'] * 1.1)) . "–₦" . number_format($service['salary_max']) . "/month. Homes in {$neighborhoods} often pay at the higher end due to larger compound sizes. {$city['salary_note']}."
                ],
                [
                    'question' => "Do housekeepers in {$city['name']} handle cooking as well as cleaning?",
                    'short_answer' => "Yes. Most housekeepers on Maids.ng in {$city['name']} handle both cleaning and basic meal preparation, though you should specify your needs during matching.",
                    'full_answer' => "In {$city['name']}, {$city['housing']}, housekeepers often combine cleaning with kitchen duties. Families in {$neighborhoods} typically expect housekeepers who can manage both tasks. If you need a specialist cook, we recommend selecting 'Cook' as your primary service."
                ],
                [
                    'question' => "Is it better to hire a live-in or live-out housekeeper in {$city['name']}?",
                    'short_answer' => "{$city['commute']}. Live-in staff are more common for larger homes, while live-out suits smaller households.",
                    'full_answer' => "{$city['unique']} In {$city['name']}, {$city['commute']}. For homes in {$neighborhoods}, many employers prefer live-in staff to avoid the uncertainty of daily commute. Live-out works well if you are near {$landmark} or in the city centre."
                ],
                [
                    'question' => "What should I check before hiring a housekeeper in {$city['name']}?",
                    'short_answer' => "Verify NIN, request references from prior employers in {$city['name']}, and conduct a trial week before finalising terms.",
                    'full_answer' => "Always request a valid National Identification Number (NIN) and verify it. Ask for references from households in {$neighborhoods} or nearby {$nearby}. On Maids.ng, every housekeeper is pre-verified, but you should still conduct a 3–7 day trial to assess compatibility with your household routines."
                ],
                [
                    'question' => "Can a housekeeper from Maids.ng work in {$nearby} if I live there?",
                    'short_answer' => "Yes. We match housekeepers who are willing to commute or live in across {$city['name']} and surrounding towns like {$nearby}.",
                    'full_answer' => "{$city['proximity']}. If you live in {$nearby}, specify this during the quiz and we will match you with staff comfortable with that location. Some staff prefer live-in arrangements for outlying areas."
                ],
            ],
            'nanny' => [
                [
                    'question' => "How much does a nanny cost in {$city['name']}?",
                    'short_answer' => "Nanny salaries in {$city['name']} range from ₦" . number_format($service['salary_min']) . " to ₦" . number_format($service['salary_max']) . " per month.",
                    'full_answer' => "In {$city['name']}, nanny salaries reflect the local cost of living and demand. {$city['salary_note']}. Live-in nannies in {$neighborhoods} typically earn ₦" . number_format($service['salary_min']) . "–₦" . number_format((int) round($service['salary_max'] * 0.7)) . "/month, while live-out nannies earn ₦" . number_format((int) round($service['salary_min'] * 1.1)) . "–₦" . number_format($service['salary_max']) . "/month."
                ],
                [
                    'question' => "Are the nannies on Maids.ng in {$city['name']} trained in first aid?",
                    'short_answer' => "Many nannies in {$city['name']} have basic first aid awareness, though formal certification varies. You can request this during matching.",
                    'full_answer' => "{$city['unique']} While Maids.ng verifies identity and references, formal first aid certification is not universal. If you require a nanny with first aid training — common for families in {$neighborhoods} — specify this in your quiz and we will prioritise candidates with documented training."
                ],
                [
                    'question' => "Can a nanny in {$city['name']} do school runs to areas like {$neighborhoods}?",
                    'short_answer' => "Yes, provided you specify the school location and pickup time during the matching quiz.",
                    'full_answer' => "In {$city['name']}, {$city['commute']}. If your child attends school in {$neighborhoods} or near {$landmark}, our matching algorithm prioritises nannies familiar with those routes. {$city['commute']} Specify whether you need the nanny to use public transport, your vehicle, or an app-based ride service."
                ],
                [
                    'question' => "What is the typical working schedule for a nanny in {$city['name']}?",
                    'short_answer' => "Most nannies work 8–12 hours daily, 5–6 days per week. Live-in nannies may be on-call for emergencies.",
                    'full_answer' => "{$city['unique']} In {$city['name']}, {$city['commute']}. Families in {$neighborhoods} often structure shifts around their own work schedules. Live-in nannies typically work longer hours but have set rest days. We recommend agreeing on exact hours, rest days, and overtime pay before the nanny starts."
                ],
                [
                    'question' => "How does Maids.ng verify nannies in {$city['name']}?",
                    'short_answer' => "Every nanny completes NIN verification and background checks before appearing in {$city['name']} search results.",
                    'full_answer' => "Maids.ng requires all nannies to submit their National Identification Number (NIN) for government verification. We also collect references and conduct phone screening. For families in {$neighborhoods}, we can additionally verify that the nanny has prior experience working in similar household types in {$city['name']}."
                ],
            ],
            'cook' => [
                [
                    'question' => "How much does a cook cost in {$city['name']}?",
                    'short_answer' => "Cook salaries in {$city['name']} range from ₦" . number_format($service['salary_min']) . " to ₦" . number_format($service['salary_max']) . " per month.",
                    'full_answer' => "{$city['salary_note']}. In {$city['name']}, experienced cooks who can prepare both local and continental meals for families in {$neighborhoods} command the top end of the range. Cooks who specialise in {$city['name']}-specific cuisines or dietary requirements may negotiate higher."
                ],
                [
                    'question' => "Can I find a cook in {$city['name']} who specialises in my native cuisine?",
                    'short_answer' => "Yes. During the quiz, specify your preferred cuisine — whether local, continental, or dietary-specific.",
                    'full_answer' => "{$city['unique']} In {$city['name']}, we place cooks who understand the food culture of the region. Families in {$neighborhoods} often request cooks familiar with {$city['name']} market ingredients and seasonal produce. Whether you need traditional meals, diabetic-friendly menus, or children's meal prep, specify this during matching."
                ],
                [
                    'question' => "Does the cook handle grocery shopping in {$city['name']}?",
                    'short_answer' => "Most cooks in {$city['name']} include grocery shopping as part of their duties, though you should confirm this before hiring.",
                    'full_answer' => "In {$city['name']}, cooks typically shop at local markets or supermarkets. For families in {$neighborhoods}, cooks may visit specific markets known for fresh produce. {$city['unique']} We recommend giving the cook a weekly budget and preferred vendor list to avoid confusion."
                ],
                [
                    'question' => "Can a cook in {$city['name']} prepare meals for guests and events?",
                    'short_answer' => "Yes. Many cooks on Maids.ng in {$city['name']} have experience with event catering and large-family dining.",
                    'full_answer' => "{$city['unique']} In {$city['name']}, cooks placed in {$neighborhoods} often handle weekend hosting and extended-family meals. If you regularly entertain guests, specify the typical headcount and meal style during the quiz so we can match you with a cook experienced in {$city['name']}-style hospitality."
                ],
                [
                    'question' => "What safety checks does Maids.ng do for cooks in {$city['name']}?",
                    'short_answer' => "NIN verification, reference calls, and basic kitchen hygiene screening.",
                    'full_answer' => "Every cook on Maids.ng undergoes NIN verification and reference checks. For {$city['name']} placements, we also verify that the cook has worked in similar household types — whether large compounds in {$neighborhoods} or apartments near {$landmark}. You can request a food hygiene trial during the first week."
                ],
            ],
            'driver' => [
                [
                    'question' => "How much does a driver cost in {$city['name']}?",
                    'short_answer' => "Driver salaries in {$city['name']} range from ₦" . number_format($service['salary_min']) . " to ₦" . number_format($service['salary_max']) . " per month.",
                    'full_answer' => "{$city['salary_note']}. In {$city['name']}, drivers familiar with routes in {$neighborhoods} and to {$landmark} earn at the higher end. {$city['commute']}. Drivers who also perform basic vehicle maintenance may negotiate premiums."
                ],
                [
                    'question' => "Do drivers on Maids.ng in {$city['name']} have valid licences?",
                    'short_answer' => "Yes. All drivers must present a valid Nigerian driver's licence and at least 3 years of driving experience.",
                    'full_answer' => "Maids.ng verifies that every driver has a valid Nigerian driver's licence. For {$city['name']} placements, we additionally check familiarity with local routes — from {$neighborhoods} to the city centre and to {$nearby}. If you need an airport-run driver, specify whether they must know the route to the nearest airport."
                ],
                [
                    'question' => "Can a driver in {$city['name']} do school runs and errands?",
                    'short_answer' => "Yes. Most drivers handle school runs, market trips, and family errands as part of their daily duties.",
                    'full_answer' => "In {$city['name']}, {$city['commute']}. Families in {$neighborhoods} typically require drivers who can navigate school routes during morning rush and handle grocery runs to markets near {$landmark}. Specify your daily route requirements during the quiz."
                ],
                [
                    'question' => "Is it better to hire a live-in or live-out driver in {$city['name']}?",
                    'short_answer' => "{$city['commute']}. Live-in drivers are ideal if you need early-morning airport runs or late-night pickups.",
                    'full_answer' => "{$city['unique']} In {$city['name']}, {$city['commute']}. For families in {$neighborhoods} who need 6am school runs or late airport pickups, live-in drivers are more reliable. Live-out works if your schedule is fixed and you live near major roads."
                ],
                [
                    'question' => "What vehicle types do drivers in {$city['name']} typically operate?",
                    'short_answer' => "Most drivers handle sedans, SUVs, and minibuses. Specify your vehicle type during matching.",
                    'full_answer' => "In {$city['name']}, drivers are experienced with the common vehicle types on local roads — from compact sedans for city driving to SUVs for {$nearby} trips. Families in {$neighborhoods} with multiple vehicles often hire drivers who can switch between manual and automatic. Specify your vehicle make and transmission during the quiz."
                ],
            ],
            'elderly-carer' => [
                [
                    'question' => "How much does an elderly carer cost in {$city['name']}?",
                    'short_answer' => "Elderly carer salaries in {$city['name']} range from ₦" . number_format($service['salary_min']) . " to ₦" . number_format($service['salary_max']) . " per month.",
                    'full_answer' => "{$city['salary_note']}. In {$city['name']}, elderly carers who have nursing experience or can manage mobility aids command higher salaries. Families in {$neighborhoods} often need live-in carers for 24-hour support, while city-centre families may manage with day-shift-only care."
                ],
                [
                    'question' => "Can an elderly carer in {$city['name']} administer medication?",
                    'short_answer' => "Some can, but this depends on training. Specify medication management needs during the quiz.",
                    'full_answer' => "{$city['unique']} In {$city['name']}, carers with basic health training can remind patients to take medication and monitor vitals. If your elderly relative requires insulin injections, wound dressing, or physiotherapy, we will match you with a carer who has documented nursing or caregiving training. Always verify this during the interview."
                ],
                [
                    'question' => "Do elderly carers on Maids.ng in {$city['name']} provide companionship?",
                    'short_answer' => "Yes. Companionship is a core part of elderly care, including conversation, walks, and social engagement.",
                    'full_answer' => "In {$city['name']}, elderly carers do more than physical support — they provide emotional companionship. {$city['unique']} Carers placed near {$landmark} or in {$neighborhoods} often accompany elderly clients on walks or to social gatherings. If your relative speaks a specific language or has cultural preferences, we match accordingly."
                ],
                [
                    'question' => "Is live-in care available for elderly patients in {$city['name']}?",
                    'short_answer' => "Yes. Live-in elderly care is the most common arrangement in {$city['name']} for families who need round-the-clock support.",
                    'full_answer' => "{$city['commute']}. In {$city['name']}, live-in elderly carers are preferred for patients with dementia, mobility issues, or chronic conditions. Families in {$neighborhoods} often set up a dedicated room for the carer. Live-out care works for active seniors who only need daytime assistance."
                ],
                [
                    'question' => "How does Maids.ng screen elderly carers in {$city['name']}?",
                    'short_answer' => "NIN verification, reference checks, and phone interviews focused on patience and medical awareness.",
                    'full_answer' => "Every elderly carer on Maids.ng is NIN-verified and reference-checked. For {$city['name']} placements, we prioritise carers with prior experience in similar households — whether caring for retirees in {$neighborhoods} or supporting families near {$landmark}. We also screen for temperament, as elderly care requires patience and emotional resilience."
                ],
            ],
            'cleaner' => [
                [
                    'question' => "How much does a cleaner cost in {$city['name']}?",
                    'short_answer' => "Cleaner salaries in {$city['name']} range from ₦" . number_format($service['salary_min']) . " to ₦" . number_format($service['salary_max']) . " per month.",
                    'full_answer' => "{$city['salary_note']}. In {$city['name']}, full-time live-in cleaners for large homes in {$neighborhoods} earn ₦" . number_format($service['salary_min']) . "–₦" . number_format((int) round($service['salary_max'] * 0.7)) . "/month. Part-time cleaners for smaller apartments near {$landmark} charge daily or weekly rates, typically ₦" . number_format((int) round($service['salary_min'] * 0.5)) . "–₦" . number_format((int) round($service['salary_min'] * 0.8)) . "/day."
                ],
                [
                    'question' => "Can I hire a cleaner for deep cleaning only in {$city['name']}?",
                    'short_answer' => "Yes. Many cleaners on Maids.ng in {$city['name']} offer one-time deep cleaning, post-event cleaning, and move-in/move-out services.",
                    'full_answer' => "{$city['unique']} In {$city['name']}, families in {$neighborhoods} often book deep cleaning before events or after the rainy season, when mould and dust accumulate. Specify the scope — windows, carpets, kitchen degreasing, or full compound scrubbing — during the quiz."
                ],
                [
                    'question' => "Do cleaners in {$city['name']} bring their own supplies?",
                    'short_answer' => "Some do, but most employers prefer to provide cleaning products to ensure quality and safety.",
                    'full_answer' => "In {$city['name']}, the standard practice is for the employer to supply cleaning products, especially for homes in {$neighborhoods} with specific hygiene standards. If you want the cleaner to bring supplies, specify this during matching and agree on reimbursement."
                ],
                [
                    'question' => "How often should I schedule cleaning for my home in {$city['name']}?",
                    'short_answer' => "Daily cleaning is standard for large homes; twice-weekly suits smaller apartments. Post-event cleaning is available on demand.",
                    'full_answer' => "{$city['unique']} In {$city['name']}, {$city['housing']}. Homes in {$neighborhoods} with large compounds often need daily cleaning due to dust and outdoor traffic. Apartments near {$landmark} may manage with 2–3 visits per week. We recommend starting with a trial schedule and adjusting based on your household size."
                ],
                [
                    'question' => "Can a cleaner in {$city['name']} also handle laundry?",
                    'short_answer' => "Yes. Most cleaners include basic laundry as part of their duties, though ironing and delicate fabric care may be extra.",
                    'full_answer' => "In {$city['name']}, cleaners typically wash, dry, and fold laundry. {$city['unique']} For homes in {$neighborhoods}, cleaners may also manage ironing for formal workwear. If you need specialist fabric care or steam pressing, mention this during the quiz."
                ],
            ],
            'laundry-person' => [
                [
                    'question' => "How much does a laundry person cost in {$city['name']}?",
                    'short_answer' => "Laundry staff salaries in {$city['name']} range from ₦" . number_format($service['salary_min']) . " to ₦" . number_format($service['salary_max']) . " per month.",
                    'full_answer' => "{$city['salary_note']}. In {$city['name']}, full-time laundry staff for large households in {$neighborhoods} earn ₦" . number_format($service['salary_min']) . "–₦" . number_format((int) round($service['salary_max'] * 0.7)) . "/month. Part-time or per-basket rates are also common for smaller homes near {$landmark}."
                ],
                [
                    'question' => "Can a laundry person in {$city['name']} handle delicate fabrics and uniforms?",
                    'short_answer' => "Yes. Specify fabric care needs during the quiz — including silk, agbada, corporate shirts, or children's uniforms.",
                    'full_answer' => "{$city['unique']} In {$city['name']}, laundry staff in {$neighborhoods} often handle a mix of traditional wear, office uniforms, and school kits. If you have delicate fabrics requiring hand-wash or dry-clean coordination, specify this during matching."
                ],
                [
                    'question' => "Do laundry staff in {$city['name']} iron and fold clothes?",
                    'short_answer' => "Yes. Ironing, folding, and wardrobe organisation are standard parts of the laundry service.",
                    'full_answer' => "In {$city['name']}, laundry staff wash, dry, iron, and fold clothes. {$city['unique']} For families in {$neighborhoods}, staff may also organise wardrobes by season or family member. Specify if you need starching, steam pressing, or special folding for office wear."
                ],
                [
                    'question' => "How does a laundry person manage large family loads in {$city['name']}?",
                    'short_answer' => "Experienced laundry staff schedule loads by fabric type and family member, often using a weekly rotation system.",
                    'full_answer' => "{$city['unique']} In {$city['name']}, laundry staff for extended families in {$neighborhoods} typically develop a schedule — whites on Monday, colours on Tuesday, traditional wear on Wednesday, etc. If your household generates heavy loads, we match you with staff who have managed similar volumes."
                ],
                [
                    'question' => "Can I hire a laundry person part-time in {$city['name']}?",
                    'short_answer' => "Yes. Part-time laundry staff are common for smaller households or those with washing machines who only need ironing help.",
                    'full_answer' => "In {$city['name']}, part-time laundry staff typically work 2–3 days per week. {$city['commute']}. For homes near {$landmark} with in-house washing machines, a part-time ironer and folder may be sufficient. Specify your machine setup and schedule during the quiz."
                ],
            ],
            'home-manager' => [
                [
                    'question' => "How much does a home manager cost in {$city['name']}?",
                    'short_answer' => "Home manager salaries in {$city['name']} range from ₦" . number_format($service['salary_min']) . " to ₦" . number_format($service['salary_max']) . " per month.",
                    'full_answer' => "{$city['salary_note']}. In {$city['name']}, experienced home managers who supervise multiple staff and handle budgets for large compounds in {$neighborhoods} command the top end. {$city['unique']} Entry-level home managers for smaller households start at ₦" . number_format($service['salary_min']) . "/month."
                ],
                [
                    'question' => "What does a home manager do in a {$city['name']} household?",
                    'short_answer' => "They supervise other staff, manage household budgets, coordinate vendors, and handle administrative tasks.",
                    'full_answer' => "{$city['unique']} In {$city['name']}, home managers for families in {$neighborhoods} typically oversee cleaners, cooks, and nannies; manage monthly grocery budgets; coordinate with plumbers, electricians, and security firms; and handle event planning. For homes near {$landmark}, they may also manage guest relations and protocol."
                ],
                [
                    'question' => "Do I need a home manager if I only have one other staff member in {$city['name']}?",
                    'short_answer' => "Usually not. A home manager is most valuable when supervising 3+ staff or managing a large compound.",
                    'full_answer' => "In {$city['name']}, {$city['housing']}. If you have only one maid or nanny, direct management is usually sufficient. A home manager becomes essential when you employ 3+ staff, run a guest house, or manage a large compound in {$neighborhoods} where coordination is complex."
                ],
                [
                    'question' => "Can a home manager in {$city['name']} handle event planning?",
                    'short_answer' => "Yes. Many home managers coordinate family events, from small dinners to large celebrations.",
                    'full_answer' => "{$city['unique']} In {$city['name']}, home managers for families in {$neighborhoods} often plan weekend gatherings, birthday parties, and seasonal events. They coordinate catering, décor, and staff scheduling. If you host frequently, specify the typical event size and frequency during the quiz."
                ],
                [
                    'question' => "What qualifications should I look for in a home manager in {$city['name']}?",
                    'short_answer' => "Prior household management experience, basic bookkeeping skills, and strong organisational ability.",
                    'full_answer' => "In {$city['name']}, ideal home managers have managed similar households — whether large compounds in {$neighborhoods} or formal residences near {$landmark}. {$city['unique']} Maids.ng verifies NIN and references; we also check for prior supervisory roles and vendor management experience."
                ],
            ],
            default => [],
        };
    }

    /**
     * Service base data.
     */
    public static function serviceData(string $serviceSlug): array
    {
        return match ($serviceSlug) {
            'housekeeper' => [
                'name' => 'Housekeeper',
                'plural' => 'Housekeepers',
                'salary_min' => 40000,
                'salary_max' => 120000,
                'short_description' => 'A housekeeper manages daily household tasks including cleaning, laundry, cooking, and general home maintenance.',
                'duties' => 'Cleaning, laundry, cooking, general home maintenance, grocery shopping, and keeping the household organised.',
            ],
            'nanny' => [
                'name' => 'Nanny',
                'plural' => 'Nannies',
                'salary_min' => 50000,
                'salary_max' => 150000,
                'short_description' => 'A nanny provides professional childcare including feeding, school runs, light housework, and child development support.',
                'duties' => 'Childcare, feeding, school runs, light housework, homework assistance, and maintaining children\'s routines.',
            ],
            'cook' => [
                'name' => 'Cook',
                'plural' => 'Cooks',
                'salary_min' => 45000,
                'salary_max' => 130000,
                'short_description' => 'A cook prepares meals, manages the kitchen, and handles grocery shopping for your household.',
                'duties' => 'Meal preparation, kitchen management, grocery shopping, menu planning, and maintaining kitchen cleanliness.',
            ],
            'driver' => [
                'name' => 'Driver',
                'plural' => 'Drivers',
                'salary_min' => 55000,
                'salary_max' => 140000,
                'short_description' => 'A household driver handles school runs, errands, airport pickups, and general family transportation.',
                'duties' => 'School runs, errands, airport pickups, general driving, vehicle maintenance, and ensuring passenger safety.',
            ],
            'elderly-carer' => [
                'name' => 'Elderly Carer',
                'plural' => 'Elderly Carers',
                'salary_min' => 50000,
                'salary_max' => 150000,
                'short_description' => 'An elderly carer provides personal care, medication management, companionship, and mobility support for senior citizens.',
                'duties' => 'Personal care, medication management, companionship, mobility support, meal preparation, and monitoring health conditions.',
            ],
            'cleaner' => [
                'name' => 'Cleaner',
                'plural' => 'Cleaners',
                'salary_min' => 35000,
                'salary_max' => 100000,
                'short_description' => 'A cleaner provides deep cleaning, post-event cleaning, move-in/move-out cleaning, and regular maintenance.',
                'duties' => 'Deep cleaning, post-event cleaning, move-in/move-out cleaning, window cleaning, and maintaining hygiene standards.',
            ],
            'laundry-person' => [
                'name' => 'Laundry Person',
                'plural' => 'Laundry Staff',
                'salary_min' => 35000,
                'salary_max' => 90000,
                'short_description' => 'Laundry staff handles washing, ironing, folding, and wardrobe management for your household.',
                'duties' => 'Washing, ironing, folding, wardrobe management, stain treatment, and fabric care.',
            ],
            'home-manager' => [
                'name' => 'Home Manager',
                'plural' => 'Home Managers',
                'salary_min' => 80000,
                'salary_max' => 200000,
                'short_description' => 'A home manager supervises other staff, manages household budgeting, handles admin tasks, and coordinates events.',
                'duties' => 'Supervising other staff, budgeting, household administration, vendor management, event coordination, and ensuring smooth household operations.',
            ],
            default => [
                'name' => 'Helper',
                'plural' => 'Helpers',
                'salary_min' => 40000,
                'salary_max' => 120000,
                'short_description' => 'A domestic helper provides household support tailored to your needs.',
                'duties' => 'General household support as specified by the employer.',
            ],
        };
    }

    /**
     * Build the full local proof section (Pillar 1) for a city × service combination.
     * This content must fail the swap test — swapping the city name must make it implausible.
     */
    public static function buildLocalProof(string $serviceSlug, string $citySlug): string
    {
        $city = self::cityData($citySlug);
        $service = self::serviceData($serviceSlug);

        $estates = implode(', ', array_slice($city['estates'], 0, 3));
        $neighborhoods = implode(', ', array_slice($city['neighborhoods'], 0, 3));
        $landmark = $city['landmarks'][0] ?? $city['name'];

        $serviceSpecific = match ($serviceSlug) {
            'housekeeper' => "In {$city['name']}, {$city['local_proof']} Housekeepers we place in {$estates} understand the expectation of maintaining large, multi-room homes common in those areas. In {$neighborhoods}, they adapt to older layouts and more compact spaces where storage and organisation skills matter. {$city['unique']}",
            'nanny' => "In {$city['name']}, {$city['local_proof']} Nannies placed near {$landmark} or in {$estates} are familiar with school routes and safety protocols for children. {$city['commute']} {$city['unique']}",
            'cook' => "In {$city['name']}, {$city['local_proof']} Cooks we match in {$estates} often serve families who entertain guests regularly and need presentation-quality meals. In {$neighborhoods}, they adapt to local market ingredients and family-style cooking. {$city['unique']}",
            'driver' => "In {$city['name']}, {$city['local_proof']} Drivers familiar with routes through {$neighborhoods} and to {$landmark} are in high demand. {$city['commute']} {$city['unique']}",
            'elderly-carer' => "In {$city['name']}, {$city['local_proof']} Elderly carers placed in {$estates} often support retirees who need mobility assistance around large compounds. {$city['unique']} In {$neighborhoods}, carers adapt to more modest homes where space management and companionship are the priority.",
            'cleaner' => "In {$city['name']}, {$city['local_proof']} Cleaners for homes in {$estates} handle larger floor areas and outdoor spaces. In {$neighborhoods}, they focus on detailed interior work and mould prevention. {$city['unique']}",
            'laundry-person' => "In {$city['name']}, {$city['local_proof']} Laundry staff in {$estates} manage high volumes of formal and traditional wear for large families. In {$neighborhoods}, they handle school uniforms and workwear with tight turnaround times. {$city['unique']}",
            'home-manager' => "In {$city['name']}, {$city['local_proof']} Home managers in {$estates} supervise staff teams of 3–5 people and coordinate with security and gardening vendors. In {$neighborhoods}, they focus on tighter household budgets and direct family coordination. {$city['unique']}",
            default => $city['local_proof'],
        };

        return $serviceSpecific;
    }

    /**
     * Build the proximity signals section (Pillar 3) for a city.
     */
    public static function buildProximity(string $serviceSlug, string $citySlug): string
    {
        $city = self::cityData($citySlug);
        $service = self::serviceData($serviceSlug);
        $nearby = implode(', ', array_slice($city['nearby_areas'], 0, 3));

        return "{$city['proximity']} We also place {$service['plural']} with families in {$nearby}. If you live in those areas, we match you with staff willing to commute or live in. {$city['commute']}";
    }

    /**
     * Build the hiring context paragraph — why this city needs this service.
     */
    public static function buildHiringContext(string $serviceSlug, string $citySlug): string
    {
        $city = self::cityData($citySlug);
        $service = self::serviceData($serviceSlug);

        return match ($serviceSlug) {
            'housekeeper' => "Families in {$city['name']} need housekeepers who understand {$city['housing']}. {$city['unique']} Whether you live in {$city['estates'][0]} or {$city['neighborhoods'][0]}, finding a housekeeper who knows the local area makes daily management smoother.",
            'nanny' => "Parents in {$city['name']} need nannies who can navigate {$city['commute']} and understand the local school landscape. {$city['unique']} From {$city['estates'][0]} to {$city['neighborhoods'][0]}, reliable childcare is essential for working families.",
            'cook' => "Households in {$city['name']} need cooks who can shop at local markets and adapt to {$city['housing']}. {$city['unique']} Whether you entertain guests in {$city['estates'][0]} or need family meals in {$city['neighborhoods'][0]}, the right cook makes the difference.",
            'driver' => "Families in {$city['name']} need drivers who know the routes from {$city['neighborhoods'][0]} to the city centre and beyond. {$city['commute']} {$city['unique']} From airport runs to school pickups, a local driver saves time and stress.",
            'elderly-carer' => "Families in {$city['name']} need elderly carers who understand the local healthcare landscape and can support seniors in {$city['housing']}. {$city['unique']} Whether your relative lives in {$city['estates'][0]} or {$city['neighborhoods'][0]}, compassionate local care matters.",
            'cleaner' => "Homes in {$city['name']} need cleaners who understand the challenges of {$city['housing']}. {$city['unique']} From deep cleaning in {$city['estates'][0]} to regular maintenance in {$city['neighborhoods'][0]}, local knowledge ensures better results.",
            'laundry-person' => "Households in {$city['name']} need laundry staff who can handle the fabric mix common in the region. {$city['unique']} From formal wear in {$city['estates'][0]} to school uniforms in {$city['neighborhoods'][0]}, reliable laundry support keeps families presentable.",
            'home-manager' => "Large households in {$city['name']} need home managers who can coordinate staff across {$city['housing']}. {$city['unique']} From vendor management in {$city['estates'][0]} to budget oversight in {$city['neighborhoods'][0]}, a skilled manager keeps everything running.",
            default => "Families in {$city['name']} need reliable {$service['plural']} who understand local conditions.",
        };
    }
}

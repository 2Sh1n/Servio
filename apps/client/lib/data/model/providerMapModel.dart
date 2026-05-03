class ProviderMapModel {
  final String id;
  final String providerId;
  final String providerName;
  final String translatedProviderName;
  final String companyName;
  final String translatedCompanyName;
  final double latitude;
  final double longitude;
  final double rating;
  final int totalServices;
  final String image;
  final double distance;

  ProviderMapModel({
    required this.id,
    required this.providerId,
    required this.providerName,
    required this.translatedProviderName,
    required this.companyName,
    required this.translatedCompanyName,
    required this.latitude,
    required this.longitude,
    required this.rating,
    required this.totalServices,
    required this.image,
    required this.distance,
  });

  factory ProviderMapModel.fromJson(Map<String, dynamic> json) {
    double _parseDouble(dynamic value) {
      if (value == null || value.toString().trim().isEmpty) return 0.0;
      return double.tryParse(value.toString()) ?? 0.0;
    }

    int _parseInt(dynamic value) {
      if (value == null || value.toString().trim().isEmpty) return 0;
      return int.tryParse(value.toString()) ?? 0;
    }

    return ProviderMapModel(
      id: json['id']?.toString() ?? '',
      providerId: json['partner_id']?.toString() ?? '',
      providerName: json['provider_name']?.toString().trim() ?? '',
      translatedProviderName:
          (json['translated_provider_name']?.toString() ?? '').isNotEmpty
              ? json['translated_provider_name']!.toString().trim()
              : (json['provider_name']?.toString() ?? '').trim(),
      companyName: (json['company_name'] ?? '').toString().trim(),
      translatedCompanyName:
          (json['translated_company_name']?.toString() ?? '').isNotEmpty
              ? json['translated_company_name']!.toString().trim()
              : (json['company_name']?.toString() ?? '').trim(),
      latitude: _parseDouble(json['latitude']),
      longitude: _parseDouble(json['longitude']),
      rating: _parseDouble(json['ratings']),
      totalServices: _parseInt(json['total_services']),
      image: json['image']?.toString() ?? '',
      distance: _parseDouble(json['distance']),
    );
  }
}

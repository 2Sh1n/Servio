class BlogCategoryModel {
  final String id;
  final String name;
  final String translatedName;
  final String slug;
  final String status;
  final String createdAt;
  final String updatedAt;
  final int blogCount;

  BlogCategoryModel({
    required this.id,
    required this.name,
    required this.translatedName,
    required this.slug,
    required this.status,
    required this.createdAt,
    required this.updatedAt,
    required this.blogCount,
  });

  factory BlogCategoryModel.fromJson(Map<String, dynamic> json) {
    return BlogCategoryModel(
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      translatedName: (json['translated_name']?.toString() ?? '').isNotEmpty
          ? json['translated_name']!.toString()
          : (json['name']?.toString() ?? ''),
      slug: json['slug']?.toString() ?? '',
      status: json['status']?.toString() ?? '1',
      createdAt: json['created_at']?.toString() ?? '',
      updatedAt: json['updated_at']?.toString() ?? '',
      blogCount: int.tryParse(json['blog_count']?.toString() ?? '0') ?? 0,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'translated_name': translatedName,
        'slug': slug,
        'status': status,
        'created_at': createdAt,
        'updated_at': updatedAt,
        'blog_count': blogCount,
      };
}

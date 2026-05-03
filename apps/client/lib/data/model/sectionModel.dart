import 'package:e_demand/app/generalImports.dart';

class Sections {
  Sections({
    required this.id,
    required this.title,
    required this.translatedTitle,
    required this.sectionType,
    required this.partners,
    required this.subCategories,
    required this.previousBookings,
    required this.onGoingBookings,
    this.sliderImage,
  });

  Sections.fromJson(Map<String?, dynamic> json) {
    id = json['id']?.toString() ?? '';
    title = json['title']?.toString() ?? '';
    translatedTitle = (json['translated_title']?.toString() ?? '').isNotEmpty
        ? json['translated_title']!.toString()
        : (json['title']?.toString() ?? '');
    sectionType = json['section_type']?.toString() ?? '';
    partners = (json['partners'] as List)
        .map((e) => Partners.fromJson(Map.from(e)))
        .toList();

    if ((json["sub_categories"] as List).isNotEmpty) {
      (json["sub_categories"] as List).forEach((final v) {
        subCategories.add(SubCategories.fromJson(v));
      });
    }
    if (json["previous_order"] != null) {
      if ((json["previous_order"] as List).isNotEmpty) {
        (json["previous_order"] as List).forEach((final v) {
          previousBookings.add(Booking.fromJson(v));
        });
      }
    }
    if (json["ongoing_order"] != null) {
      if ((json["ongoing_order"] as List).isNotEmpty) {
        (json["ongoing_order"] as List).forEach((final v) {
          onGoingBookings.add(Booking.fromJson(v));
        });
      }
    }

    if (json["banner"] != null) {
      if ((json["banner"] as List).isNotEmpty) {
        sliderImage = SliderImage.fromJson((json["banner"] as List).first);
      }
    }
  }

  String? id;
  String? title;
  String? translatedTitle;
  String? sectionType;
  List<Partners> partners = <Partners>[];
  List<SubCategories> subCategories = <SubCategories>[];
  List<Booking> previousBookings = <Booking>[];
  List<Booking> onGoingBookings = <Booking>[];
  SliderImage? sliderImage;
}

class Partners {
  Partners(
      {required this.id,
      required this.username,
      required this.translatedUsername,
      required this.image,
      required this.promoCode,
      required this.discount,
      required this.discountType,
      required this.companyName,
      required this.translatedCompanyName,
      required this.numberOfRating,
      required this.distance,
      required this.bannerImage,
      required this.totalServices,
      required this.averageRating});

  Partners.fromJson(final Map<String?, dynamic> json) {
    id = json["id"]?.toString() ?? '';
    username = json["username"]?.toString() ?? '';
    translatedUsername =
        (json['translated_username']?.toString() ?? '').isNotEmpty
            ? json['translated_username']!.toString()
            : (json['username']?.toString() ?? '');
    discountType = json["discount_type"]?.toString() ?? '';
    image = json["image"]?.toString() ?? '';
    promoCode = json["promo_code"]?.toString() ?? '';
    discount = json["discount"].toString();
    companyName = json["company_name"]?.toString() ?? '';

    translatedCompanyName =
        (json['translated_company_name']?.toString() ?? '').isNotEmpty
            ? json['translated_company_name']!.toString()
            : (json['company_name']?.toString() ?? '');
    discountType = json["discount_type"]?.toString() ?? '';
    bannerImage = json["banner_image"]?.toString() ?? '';
    numberOfRating = json["number_of_rating"]?.toString() ?? '';
    discount = json["distance"]?.toString() ?? '';
    totalServices = json["total_services"]?.toString() ?? '';
    averageRating = (json['average_rating'] ?? "0").toString();
  }

  String? id;
  String? username;
  String? translatedUsername;
  String? image;
  String? promoCode;
  String? discount;
  String? discountType;
  String? companyName;
  String? translatedCompanyName;
  String? bannerImage;
  String? numberOfRating;
  String? distance;
  String? totalServices;
  String? averageRating;
}

class SubCategories {
  SubCategories({
    required this.id,
    required this.parentId,
    required this.name,
    required this.translatedName,
    required this.image,
    required this.slug,
    required this.totalProviders,
  });

  SubCategories.fromJson(final Map<String?, dynamic> json) {
    id = json["id"]?.toString() ?? '';
    parentId = json["parent_id"]?.toString() ?? '';
    name = json["name"]?.toString() ?? '';

    translatedName = (json['translated_name']?.toString() ?? '').isNotEmpty
        ? json['translated_name']!.toString()
        : (json['name']?.toString() ?? '');
    image = json["image"]?.toString() ?? '';
    slug = json["slug"]?.toString() ?? '';
    totalProviders = json["total_providers"]?.toString() ?? '';
  }

  String? id;
  String? parentId;
  String? name;
  String? translatedName;
  String? image;
  String? slug;
  String? totalProviders;
}

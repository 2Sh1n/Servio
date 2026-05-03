import 'package:e_demand/app/generalImports.dart';

class Providers {
  Providers(
      {this.id,
      this.providerId,
      this.companyName,
      this.translatedCompanyName,
      this.partnerName,
      this.translatedPartnerName,
      this.image,
      this.advanceBookingDays,
      this.numberOfMembers,
      this.ratings,
      this.numberOfRatings,
      this.visitingCharge,
      this.isAvailableNow,
      this.about,
      this.translatedAbout,
      this.address,
      this.fiveStar,
      this.fourStar,
      this.threeStar,
      this.twoStar,
      this.oneStar,
      this.isBookmark,
      this.bannerImage,
      this.sundayIsOpen,
      this.sundayOpeningTime,
      this.sundayClosingTime,
      this.saturdayIsOpen,
      this.saturdayOpeningTime,
      this.saturdayClosingTime,
      this.fridayIsOpen,
      this.fridayOpeningTime,
      this.fridayClosingTime,
      this.thursdayIsOpen,
      this.thursdayOpeningTime,
      this.thursdayClosingTime,
      this.wednesdayIsOpen,
      this.wednesdayOpeningTime,
      this.wednesdayClosingTime,
      this.tuesdayIsOpen,
      this.tuesdayOpeningTime,
      this.tuesdayClosingTime,
      this.mondayIsOpen,
      this.mondayOpeningTime,
      this.mondayClosingTime,
      this.businessDayInfo,
      this.otherImagesOfTheService,
      this.longDescription,
      this.translatedLongDescription,
      this.latitude,
      this.longitude,
      this.isAvailableAtLocation,
      this.isAtStoreAvailable,
      this.isAtDoorstepAvailable,
      this.isPostBookingChatAllowed,
      this.isPreBookingChatAllowed,
      this.distance,
      this.totalServices,
      this.slug,
      this.promocode});

  Providers.fromJson(final Map<String?, dynamic> json) {
    id = json["id"]?.toString() ?? "0";
    providerId = json["partner_id"]?.toString() ?? "0";
    companyName = json["company_name"]?.toString() ?? '';
    translatedCompanyName =
        (json["translated_company_name"]?.toString() ?? '').isNotEmpty
            ? json["translated_company_name"]!.toString()
            : (json["company_name"]?.toString() ?? '');
    partnerName =
        json["partner_name"]?.toString() ?? json["username"]?.toString() ?? '';

    translatedPartnerName =
        (json["translated_username"]?.toString() ?? '').isNotEmpty
            ? json["translated_username"].toString()
            : (json["translated_partner_name"]?.toString() ?? '').isNotEmpty
                ? json["translated_partner_name"].toString()
                : partnerName;

    image = json["image"]?.toString() ?? '';
    about = json["about"]?.toString() ?? '';
    translatedAbout = (json["translated_about"]?.toString() ?? '').isNotEmpty
        ? json["translated_about"]!.toString()
        : (json["about"]?.toString() ?? '');
    address = json["address"]?.toString() ?? '';
    advanceBookingDays = json["advance_booking_days"]?.toString() ?? '';
    numberOfMembers = json["number_of_members"]?.toString() ?? '';
    ratings =
        double.parse(json["ratings"]?.toString() ?? '0.0').toStringAsFixed(1);
    numberOfRatings = json["number_of_ratings"]?.toString() ?? '0';
    visitingCharge = json["visiting_charges"]?.toString() ?? '0';
    isAvailableNow = json["is_available_now"]?.toString() == "1";
    oneStar = json["1_star"]?.toString() ?? '0';
    twoStar = json["2_star"]?.toString() ?? '0';
    threeStar = json["3_star"]?.toString() ?? '0';
    fourStar = json["4_star"]?.toString() ?? '0';
    fiveStar = json["5_star"]?.toString() ?? '0';
    isBookmark = json["is_favorite"]?.toString() ?? "0";
    bannerImage = json["banner_image"]?.toString() ?? '';
    sundayIsOpen = json["sunday_is_open"];
    sundayOpeningTime = json["sunday_opening_time"];
    sundayClosingTime = json["sunday_closing_time"];
    saturdayIsOpen = json["saturday_is_open"];
    saturdayOpeningTime = json["saturday_opening_time"];
    saturdayClosingTime = json["saturday_closing_time"];
    fridayIsOpen = json["friday_is_open"];
    fridayOpeningTime = json["friday_opening_time"];
    fridayClosingTime = json["friday_closing_time"];
    thursdayIsOpen = json["thursday_is_open"];
    thursdayOpeningTime = json["thursday_opening_time"];
    thursdayClosingTime = json["thursday_closing_time"];
    wednesdayIsOpen = json["wednesday_is_open"];
    wednesdayOpeningTime = json["wednesday_opening_time"];
    wednesdayClosingTime = json["wednesday_closing_time"];
    tuesdayIsOpen = json["tuesday_is_open"];
    tuesdayOpeningTime = json["tuesday_opening_time"];
    tuesdayClosingTime = json["tuesday_closing_time"];
    mondayIsOpen = json["monday_is_open"];
    mondayOpeningTime = json["monday_opening_time"];
    mondayClosingTime = json["monday_closing_time"];
    longDescription = json["long_description"]?.toString() ?? '';
    translatedLongDescription =
        (json["translated_long_description"]?.toString() ?? '').isNotEmpty
            ? json["translated_long_description"]!.toString()
            : (json["long_description"]?.toString() ?? '');
    latitude = json["latitude"];
    longitude = json["longitude"];
    isAtDoorstepAvailable = json["at_doorstep"];
    isAtStoreAvailable = json["at_store"];
    isAvailableAtLocation = json["is_Available_at_location"] ?? "0";
    isPreBookingChatAllowed = json["pre_booking_chat"] ?? "0";
    isPostBookingChatAllowed = json["post_booking_chat"] ?? "0";
    distance = json["distance"] ?? "0";
    slug = json['slug'] ?? "0";
    totalServices = (json["total_services"] ?? 0).toString();

    if (json["other_images"] != null) {
      otherImagesOfTheService = <String>[];
      json["other_images"].forEach((final v) {
        otherImagesOfTheService!.add(v);
      });
    } else {
      otherImagesOfTheService = <String>[];
    }
    if (json["services"] != null) {
      services = <Services>[];
      services = (json["services"] as List)
          .map((e) => Services.fromJson(Map.from(e)))
          .toList();
    } else {
      services = <Services>[];
    }

    if (json['promocode'] != null) {
      promocode = <Promocode>[];
      promocode = (json["promocode"] as List)
          .map((e) => Promocode.fromJson(Map.from(e)))
          .toList();
    } else {
      promocode = <Promocode>[];
    }
    businessDayInfo = [
      {
        "day": "monday",
        "isOpen": mondayIsOpen,
        "openingTime": mondayOpeningTime,
        "closingTime": mondayClosingTime,
      },
      {
        "day": "tuesday",
        "isOpen": tuesdayIsOpen,
        "openingTime": tuesdayOpeningTime,
        "closingTime": tuesdayClosingTime,
      },
      {
        "day": "wednesday",
        "isOpen": wednesdayIsOpen,
        "openingTime": wednesdayOpeningTime,
        "closingTime": wednesdayClosingTime,
      },
      {
        "day": "thursday",
        "isOpen": thursdayIsOpen,
        "openingTime": thursdayOpeningTime,
        "closingTime": thursdayClosingTime,
      },
      {
        "day": "friday",
        "isOpen": fridayIsOpen,
        "openingTime": fridayOpeningTime,
        "closingTime": fridayClosingTime,
      },
      {
        "day": "saturday",
        "isOpen": saturdayIsOpen,
        "openingTime": saturdayOpeningTime,
        "closingTime": saturdayClosingTime,
      },
      {
        "day": "sunday",
        "isOpen": sundayIsOpen,
        "openingTime": sundayOpeningTime,
        "closingTime": sundayClosingTime,
      },
    ].map((e) => DayInfo.fromMap(e)).toList();
  }

  String? id;
  String? providerId;
  String? companyName;
  String? translatedCompanyName;
  String? partnerName;
  String? translatedPartnerName;
  String? image;
  String? advanceBookingDays;
  String? numberOfMembers;
  String? ratings;
  String? numberOfRatings;
  String? visitingCharge;
  String? about;
  String? translatedAbout;
  String? address;
  bool? isAvailableNow;
  String? oneStar;
  String? twoStar;
  String? threeStar;
  String? fourStar;
  String? fiveStar;
  String? isBookmark;
  String? bannerImage;
  String? sundayIsOpen;
  String? sundayOpeningTime;
  String? sundayClosingTime;
  String? saturdayIsOpen;
  String? saturdayOpeningTime;
  String? saturdayClosingTime;
  String? fridayIsOpen;
  String? fridayOpeningTime;
  String? fridayClosingTime;
  String? thursdayIsOpen;
  String? thursdayOpeningTime;
  String? thursdayClosingTime;
  String? wednesdayIsOpen;
  String? wednesdayOpeningTime;
  String? wednesdayClosingTime;
  String? tuesdayIsOpen;
  String? tuesdayOpeningTime;
  String? tuesdayClosingTime;
  String? mondayIsOpen;
  String? mondayOpeningTime;
  String? mondayClosingTime;
  List<String>? otherImagesOfTheService;
  List<DayInfo>? businessDayInfo;
  List<Services>? services;
  String? longDescription;
  String? translatedLongDescription;
  String? latitude;
  String? longitude;
  String? isAvailableAtLocation;
  String? isAtStoreAvailable;
  String? isPreBookingChatAllowed;
  String? isPostBookingChatAllowed;
  String? isAtDoorstepAvailable;
  String? distance;
  String? totalServices;
  String? slug;
  List<Promocode>? promocode;
}

class DayInfo {
  String? day;
  String? isOpen;
  String? openingTime;
  String? closingTime;

  DayInfo({
    this.day,
    this.isOpen,
    this.openingTime,
    this.closingTime,
  });

  factory DayInfo.fromMap(Map<String, dynamic> json) => DayInfo(
        day: json["day"],
        isOpen: json["isOpen"],
        openingTime: json["openingTime"],
        closingTime: json["closingTime"],
      );
}

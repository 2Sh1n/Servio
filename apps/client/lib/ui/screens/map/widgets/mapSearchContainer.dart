import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class MapSearchContainer extends StatefulWidget {
  final String hint;
  final VoidCallback? onTap;
  final bool autoFocus;
  final void Function(String value)? onSearchTextChanged;
  final Color? hintTextColor;
  final double height;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry margin;
  final String? initialText;
  const MapSearchContainer({
    super.key,
    required this.hint,
    this.autoFocus = false,
    this.onSearchTextChanged,
    this.onTap,
    this.hintTextColor,
    this.height = 50,
    this.padding = const EdgeInsets.symmetric(horizontal: 15, vertical: 10),
    this.margin = const EdgeInsets.all(15),
    this.initialText,
  });

  @override
  State<MapSearchContainer> createState() => _MapSearchContainerState();
}

class _MapSearchContainerState extends State<MapSearchContainer> {
  late final TextEditingController _searchController =
      TextEditingController(text: widget.initialText);
  final ValueNotifier _showCloseButton = ValueNotifier(false);

  String lastSearchText = '';
  Timer? _debounce;

  @override
  void initState() {
    _searchController.addListener(() {
      if (_searchController.text.trim().isEmpty) {
        _showCloseButton.value = false;
      } else {
        _showCloseButton.value = true;
      }
      if (_debounce?.isActive ?? false) _debounce?.cancel();
      _debounce = Timer(const Duration(milliseconds: 250), () {
        if (_searchController.text.trim().isNotEmpty &&
            _searchController.text.trim() != lastSearchText) {
          widget.onSearchTextChanged?.call(_searchController.text);
          lastSearchText = _searchController.text.trim();
        } else {
          widget.onSearchTextChanged?.call(_searchController.text);
        }
      });
    });
    super.initState();
  }

  @override
  void dispose() {
    _searchController.dispose();
    _debounce?.cancel();
    _showCloseButton.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return CustomInkWellContainer(
      onTap: widget.onTap,
      borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf10),
      child: CustomContainer(
        height: widget.height,
        color: context.colorScheme.secondaryColor,
        borderRadius: UiUtils.borderRadiusOf10,
        border: Border.all(color: context.colorScheme.blackColor),
        margin: widget.margin,
        padding: widget.padding,
        alignment: Alignment.center,
        width: double.maxFinite,
        child: Row(
          spacing: 12,
          children: [
            CustomSvgPicture(
              svgImage: AppAssets.search,
              color: context.colorScheme.accentColor,
            ),
            Expanded(
              child: CustomSizedBox(
                height: 20,
                child: TextFormField(
                  autofocus: widget.autoFocus,
                  controller: _searchController,
                  cursorHeight: 14,
                  onTapOutside: (event) {
                    FocusManager.instance.primaryFocus?.unfocus();
                  },
                  enabled: widget.onTap == null,
                  style: TextStyle(
                    fontSize: 14,
                    color: context.colorScheme.lightGreyColor,
                    fontWeight: FontWeight.w600,
                  ),
                  decoration: InputDecoration(
                    isDense: true,
                    contentPadding: EdgeInsets.zero,
                    filled: false,
                    hintText: widget.hint,
                    hintStyle: TextStyle(
                      fontSize: 14,
                      color: widget.hintTextColor ??
                          context.colorScheme.lightGreyColor,
                    ),
                    border: InputBorder.none,
                    suffixIconConstraints: const BoxConstraints(
                      minHeight: 20,
                      minWidth: 20,
                    ),
                    suffixIcon: ValueListenableBuilder(
                      valueListenable: _showCloseButton,
                      builder: (context, value, child) {
                        if (value) {
                          return GestureDetector(
                            onTap: () {
                              _searchController.clear();
                              FocusManager.instance.primaryFocus?.unfocus();
                            },
                            child: Icon(
                              Icons.close_rounded,
                              color: context.colorScheme.primary,
                              size: 20,
                            ),
                          );
                        } else {
                          return const SizedBox.shrink();
                        }
                      },
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

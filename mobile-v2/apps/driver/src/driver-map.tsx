import { useEffect, useMemo, useState } from "react";
import { View, type ViewStyle } from "react-native";

import type { DriverMapDiagnostics } from "@ride360/diagnostics";
import { Card, Text } from "@ride360/ui";

import {
  buildDashboardMapState,
  type ActiveRideOffer,
  type DashboardMapMarker,
  type DashboardMapRegion,
  type DriverRide,
  type LocationPoint,
} from "./driver-dashboard";
import {
  buildMapDiagnostics,
  shouldUseGoogleMapProvider,
  type DriverMapRuntimeConfig,
} from "./driver-map-diagnostics";

type MapsModule = typeof import("react-native-maps");

type MapLoadState =
  | { status: "loading"; maps?: undefined; error?: undefined }
  | { status: "ready"; maps: MapsModule; error?: undefined }
  | { status: "failed"; maps?: undefined; error: string };

export function DriverMapPreview({
  activeOffer,
  activeRide,
  currentLocation,
  lastNavigationOpenUrlProvider,
  mapConfig,
  navigationError,
  onDiagnostics,
  selectedNavigationProvider,
}: {
  activeOffer: ActiveRideOffer | null;
  activeRide: DriverRide | null;
  currentLocation: LocationPoint | null;
  lastNavigationOpenUrlProvider?: string;
  mapConfig: DriverMapRuntimeConfig;
  navigationError?: string;
  onDiagnostics?: (diagnostics: DriverMapDiagnostics) => void;
  selectedNavigationProvider?: string;
}) {
  return (
    <Card>
      <Text variant="subtitle">რუკა</Text>
      <DriverMapCanvas
        activeOffer={activeOffer}
        activeRide={activeRide}
        currentLocation={currentLocation}
        lastNavigationOpenUrlProvider={lastNavigationOpenUrlProvider}
        mapConfig={mapConfig}
        navigationError={navigationError}
        onDiagnostics={onDiagnostics}
        selectedNavigationProvider={selectedNavigationProvider}
        showCaption={false}
        style={{
          borderColor: "#d7dce5",
          borderRadius: 8,
          borderWidth: 1,
          height: 220,
          overflow: "hidden",
        }}
      />
      <Text variant="caption">
        {activeOffer || activeRide || currentLocation
          ? "რუკაზე ჩანს მძღოლი, აყვანის ან დანიშნულების წერტილები."
          : "წერტილები გამოჩნდება ცვლის დაწყების ან შეკვეთის მიღების შემდეგ."}
      </Text>
    </Card>
  );
}

export function DriverMapCanvas({
  activeOffer,
  activeRide,
  currentLocation,
  fullScreenRouteActive = false,
  mapConfig,
  navigationError,
  lastNavigationOpenUrlProvider,
  onDiagnostics,
  selectedNavigationProvider,
  showCaption = true,
  style,
}: {
  activeOffer: ActiveRideOffer | null;
  activeRide: DriverRide | null;
  currentLocation: LocationPoint | null;
  fullScreenRouteActive?: boolean;
  mapConfig: DriverMapRuntimeConfig;
  navigationError?: string;
  lastNavigationOpenUrlProvider?: string;
  onDiagnostics?: (diagnostics: DriverMapDiagnostics) => void;
  selectedNavigationProvider?: string;
  showCaption?: boolean;
  style?: ViewStyle;
}) {
  const [loadState, setLoadState] = useState<MapLoadState>({
    status: "loading",
  });
  const [mapReady, setMapReady] = useState(false);
  const [mapLoaded, setMapLoaded] = useState(false);
  const [showTileWarning, setShowTileWarning] = useState(false);
  const [lastRegion, setLastRegion] = useState<DashboardMapRegion | null>(null);
  const mapState = useMemo(
    () => buildDashboardMapState({ activeOffer, activeRide, currentLocation }),
    [activeOffer, activeRide, currentLocation],
  );
  const googleProviderEnabled = shouldUseGoogleMapProvider(mapConfig);

  useEffect(() => {
    let mounted = true;
    void import("react-native-maps")
      .then((maps) => {
        if (!mounted) return;
        setLoadState({ status: "ready", maps });
      })
      .catch((error: unknown) => {
        if (!mounted) return;
        setLoadState({
          status: "failed",
          error: error instanceof Error ? error.message : String(error),
        });
      });

    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    onDiagnostics?.(
      buildMapDiagnostics({
        activeOffer,
        activeRide,
        currentLocation,
        errorMessage:
          loadState.status === "failed" ? loadState.error : undefined,
        fullScreenRouteActive,
        googleProviderEnabled,
        lastNavigationOpenUrlProvider,
        lastRegion: lastRegion ?? mapState.region,
        loaded: mapLoaded,
        mapConfig,
        navigationError,
        ready: mapReady,
        selectedNavigationProvider,
      }),
    );
  }, [
    activeOffer,
    activeRide,
    currentLocation,
    fullScreenRouteActive,
    googleProviderEnabled,
    lastNavigationOpenUrlProvider,
    lastRegion,
    loadState,
    mapConfig,
    mapLoaded,
    mapReady,
    mapState.region,
    navigationError,
    onDiagnostics,
    selectedNavigationProvider,
  ]);

  useEffect(() => {
    if (loadState.status !== "ready" || !googleProviderEnabled || mapLoaded) {
      setShowTileWarning(false);
      return;
    }

    const timer = setTimeout(() => {
      setShowTileWarning(true);
    }, 8000);

    return () => clearTimeout(timer);
  }, [googleProviderEnabled, loadState.status, mapLoaded]);

  if (loadState.status !== "ready") {
    return (
      <View
        style={[
          {
            alignItems: "center",
            backgroundColor: "#eef2f7",
            justifyContent: "center",
            minHeight: 220,
            padding: 16,
          },
          style,
        ]}
      >
        <Text variant="caption">
          {loadState.status === "loading"
            ? "რუკა იტვირთება."
            : "რუკის ჩატვირთვა ვერ მოხერხდა, მაგრამ შეკვეთის დეტალები ხელმისაწვდომია."}
        </Text>
        {loadState.status === "failed" ? (
          <Text variant="caption">{loadState.error}</Text>
        ) : null}
      </View>
    );
  }

  const MapView = loadState.maps.default;
  const Marker = loadState.maps.Marker;
  const Polyline = loadState.maps.Polyline;
  const provider = googleProviderEnabled
    ? loadState.maps.PROVIDER_GOOGLE
    : undefined;
  const route = routeCoordinates(mapState.markers);
  const mapKey = mapState.markers
    .map((marker) => `${marker.id}:${marker.lat}:${marker.lng}`)
    .join("|");

  return (
    <View style={style}>
      <MapView
        key={mapKey}
        initialRegion={mapState.region}
        mapType="standard"
        onMapLoaded={() => {
          setMapLoaded(true);
          setShowTileWarning(false);
        }}
        onMapReady={() => setMapReady(true)}
        onRegionChangeComplete={(region) => {
          setLastRegion({
            latitude: region.latitude,
            longitude: region.longitude,
            latitudeDelta: region.latitudeDelta,
            longitudeDelta: region.longitudeDelta,
          });
        }}
        provider={provider}
        showsMyLocationButton={Boolean(currentLocation)}
        showsUserLocation={Boolean(currentLocation)}
        style={{ flex: 1 }}
      >
        {mapState.markers.map((marker) => (
          <Marker
            coordinate={{ latitude: marker.lat, longitude: marker.lng }}
            key={marker.id}
            pinColor={pinColor(marker.kind)}
            title={marker.label}
          />
        ))}
        {route.length >= 2 ? (
          <Polyline
            coordinates={route}
            strokeColor="#1557d8"
            strokeWidth={3}
          />
        ) : null}
      </MapView>
      {showCaption ? (
        <Text variant="caption">
          {mapState.markers.length > 0
            ? "რუკაზე ჩანს მძღოლი, აყვანის ან დანიშნულების წერტილები."
            : "წერტილები გამოჩნდება ცვლის დაწყების ან შეკვეთის მიღების შემდეგ."}
        </Text>
      ) : null}
      {showCaption && googleProviderEnabled && showTileWarning ? (
        <Text variant="caption">
          რუკის ჩატვირთვა ვერ მოხერხდა. შეამოწმეთ Google Maps API key /
          ინტერნეტი.
        </Text>
      ) : null}
    </View>
  );
}

function routeCoordinates(markers: DashboardMapMarker[]) {
  return markers
    .filter((marker) => marker.kind === "pickup" || marker.kind === "dropoff")
    .map((marker) => ({
      latitude: marker.lat,
      longitude: marker.lng,
    }));
}

function pinColor(kind: DashboardMapMarker["kind"]) {
  switch (kind) {
    case "driver":
      return "#1557d8";
    case "pickup":
      return "#198754";
    case "dropoff":
      return "#c2410c";
    default:
      return "#1557d8";
  }
}

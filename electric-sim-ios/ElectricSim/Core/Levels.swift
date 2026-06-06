//
//  Levels.swift
//  ElectricSim — Core
//
//  დონეებისა და კომპონენტების ბიბლიოთეკის მოდელი + JSON ჩამტვირთავი.
//

import Foundation

// MARK: - Component template (components.json)

public struct ComponentTemplate: Codable, Identifiable, Sendable {
    public let id: String
    public let kind: ComponentKind
    public let name: String
    public var ratingA: Double?
    public var curve: BreakerCurve?
    public var mAtrip: Double?
    public var powerW: Double?
    public var requiresPE: Bool?
    public var poles: Int?
    public var leakageMa: Double?
    public var faultShortToN: Bool?

    /// შაბლონიდან კონკრეტული კომპონენტის შექმნა უნიკალური id-ით.
    public func makeComponent(instanceID: String, phase: Phase = .single) -> Component {
        switch kind {
        case .supply:
            return ComponentFactory.supply(id: instanceID, phase: phase)
        case .mainSwitch:
            return ComponentFactory.mainSwitch(id: instanceID, phase: phase)
        case .mcb:
            return ComponentFactory.mcb(id: instanceID, ratingA: ratingA ?? 16, curve: curve ?? .B)
        case .rcbo:
            return ComponentFactory.rcbo(id: instanceID, ratingA: ratingA ?? 16, curve: curve ?? .B,
                                         mAtrip: mAtrip ?? Electrical.standardRCDmA)
        case .rcd:
            return ComponentFactory.rcd(id: instanceID, ratingA: ratingA ?? 40,
                                        mAtrip: mAtrip ?? Electrical.standardRCDmA)
        case .spd:
            return ComponentFactory.spd(id: instanceID)
        case .busbar:
            return ComponentFactory.busbar(id: instanceID, conductor: .L, slots: poles ?? 4)
        case .lamp:
            return ComponentFactory.lamp(id: instanceID, powerW: powerW ?? 60,
                                         requiresPE: requiresPE ?? true, leakageMa: leakageMa)
        case .socket:
            return ComponentFactory.socket(id: instanceID, powerW: powerW ?? 2300, leakageMa: leakageMa)
        case .motor:
            // 3-ფაზიანი მოტორი (Phase 3) — გამარტივებული
            let ports = [
                Port(id: "\(instanceID).L1", conductor: .L1, side: .single, name: "L1"),
                Port(id: "\(instanceID).L2", conductor: .L2, side: .single, name: "L2"),
                Port(id: "\(instanceID).L3", conductor: .L3, side: .single, name: "L3"),
                Port(id: "\(instanceID).N", conductor: .N, side: .single, name: "N"),
                Port(id: "\(instanceID).PE", conductor: .PE, side: .single, name: "PE")
            ]
            return Component(id: instanceID, kind: .motor, name: name, poles: 3,
                             powerW: powerW ?? 3000, requiresPE: true, ports: ports)
        }
    }
}

// MARK: - Level

public struct PaletteEntry: Codable, Identifiable, Sendable {
    public var id: String { templateId + "#" + String(max) }
    public let templateId: String
    public let max: Int                 // მაქს. რამდენი დაიდება ფარზე
    public var csaOptions: [Double]?    // დასაშვები კაბელის კვეთები (mm²)
}

public struct LevelGoal: Codable, Sendable {
    /// რომელი დატვირთვები უნდა აანთდეს და რამდენი (kind → count).
    public let poweredLoads: [String: Int]
    public let description: String
}

public struct Level: Codable, Identifiable, Sendable {
    public let id: String
    public let index: Int
    public let title: String
    public let brief: String       // დავალება ქართულად
    public let hint: String        // მინიშნება
    public let phase: Phase
    public let palette: [PaletteEntry]
    public let goal: LevelGoal
}

// MARK: - Data loader

public enum DataError: Error, CustomStringConvertible {
    case missingResource(String)
    public var description: String {
        switch self {
        case .missingResource(let name): return "რესურსი ვერ მოიძებნა: \(name)"
        }
    }
}

public enum GameData {

    /// რესურსების bundle — SwiftPM-ში `Bundle.module`, Xcode აპში `Bundle.main`.
    static var resourceBundle: Bundle {
        #if SWIFT_PACKAGE
        return Bundle.module
        #else
        return Bundle.main
        #endif
    }

    private static func loadJSON(_ name: String) throws -> Data {
        if let url = resourceBundle.url(forResource: name, withExtension: "json") {
            return try Data(contentsOf: url)
        }
        // fallback: main bundle (Xcode app-ში რესურსი ფლატ-კოპიითაა)
        if let url = Bundle.main.url(forResource: name, withExtension: "json") {
            return try Data(contentsOf: url)
        }
        throw DataError.missingResource(name)
    }

    public static func loadTemplates() throws -> [String: ComponentTemplate] {
        let data = try loadJSON("components")
        let list = try JSONDecoder().decode([ComponentTemplate].self, from: data)
        return Dictionary(uniqueKeysWithValues: list.map { ($0.id, $0) })
    }

    public static func loadLevels() throws -> [Level] {
        let data = try loadJSON("levels")
        let levels = try JSONDecoder().decode([Level].self, from: data)
        return levels.sorted { $0.index < $1.index }
    }
}

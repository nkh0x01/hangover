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

public enum LevelMode: String, Codable, Sendable {
    case build       // ცარიელი ფარი — ააწყობ ნულიდან
    case faultFind   // წინასწარ აწყობილი ფარი დეფექტით — იპოვე და გაასწორე
}

// MARK: - Pre-built board (fault-finding დონეებისთვის)

/// ფეხის მისამართი წინასწარ აწყობილ ფარში: კომპონენტის id + ფეხის სუფიქსი
/// (ისე, როგორც `ComponentFactory` აგენერირებს, მაგ. "Lin", "out", "PE").
public struct PortRef: Codable, Sendable {
    public let c: String   // კომპონენტის instance id
    public let p: String   // ფეხის სუფიქსი
    public var portID: String { "\(c).\(p)" }
}

public struct PrebuiltWire: Codable, Sendable {
    public let from: PortRef
    public let to: PortRef
    public let csa: Double
    public let color: String?   // WireColor rawValue; nil → გამტარიდან გამოითვლება
}

public struct PrebuiltComponent: Codable, Sendable {
    public let templateId: String
    public let id: String
    public let leakageMa: Double?      // დეფექტი: გაჟონვა
    public let faultShortToN: Bool?    // დეფექტი: შიდა L→N მოკლე ჩართვა
}

public struct PrebuiltBoard: Codable, Sendable {
    public let components: [PrebuiltComponent]
    public let wires: [PrebuiltWire]
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
    public let mode: LevelMode?    // nil → .build
    public let prebuilt: PrebuiltBoard?

    public var resolvedMode: LevelMode { mode ?? .build }

    /// დონის საწყისი ფარი: ან წინასწარ აწყობილი (faultFind), ან მხოლოდ კვება (build).
    public func initialBoard(templates: [String: ComponentTemplate]) -> Board {
        var board = Board(phase: phase)
        guard let pre = prebuilt else {
            board.add(ComponentFactory.supply(id: "supply", phase: phase))
            return board
        }
        for pc in pre.components {
            guard let t = templates[pc.templateId] else { continue }
            var comp = t.makeComponent(instanceID: pc.id, phase: phase)
            if let leak = pc.leakageMa { comp.leakageMa = leak }
            if let short = pc.faultShortToN { comp.faultShortToN = short }
            board.add(comp)
        }
        if board.supply == nil {
            board.add(ComponentFactory.supply(id: "supply", phase: phase))
        }
        for w in pre.wires {
            let color = w.color.flatMap { WireColor(rawValue: $0) }
                ?? WireColor.standard(for: board.port(w.from.portID)?.conductor ?? .L)
            board.connect(w.from.portID, w.to.portID, csaMm2: w.csa, color: color)
        }
        return board
    }
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
